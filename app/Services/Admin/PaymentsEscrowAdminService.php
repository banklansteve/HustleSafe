<?php

namespace App\Services\Admin;

use App\Models\PaymentEscrow;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Payments\EscrowPaymentService;
use App\Services\Payments\WalletService;
use App\Support\NgnMoney;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentsEscrowAdminService
{
    public function __construct(
        private readonly EscrowPaymentService $escrowPayments,
        private readonly WalletService $wallets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(): array
    {
        $fundedEscrow = PaymentEscrow::query()->whereIn('status', ['funded', 'held'])->sum('amount_minor');
        $releasedEscrow = PaymentEscrow::query()->where('status', 'released')->sum('released_minor');
        $walletBalances = (int) Wallet::query()->sum('balance_minor');
        $pendingWithdrawals = (int) \App\Models\WalletWithdrawal::query()->whereIn('status', ['pending', 'processing'])->sum('amount_minor');

        return [
            'escrow_held' => NgnMoney::format((int) $fundedEscrow),
            'escrow_held_minor' => (int) $fundedEscrow,
            'released_total' => NgnMoney::format((int) $releasedEscrow),
            'wallet_balances' => NgnMoney::format($walletBalances),
            'pending_withdrawals' => NgnMoney::format($pendingWithdrawals),
            'paystack_enabled' => app(\App\Services\Payments\PaystackClient::class)->enabled(),
        ];
    }

    public function escrowList(Request $request): LengthAwarePaginator
    {
        $query = PaymentEscrow::query()
            ->with(['quest:id,title,reference_code', 'client:id,name,email', 'freelancer:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('reference', 'like', '%'.$search.'%')
                    ->orWhereHas('quest', fn (Builder $quest) => $quest->where('title', 'like', '%'.$search.'%'))
                    ->orWhereHas('client', fn (Builder $u) => $u->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('freelancer', fn (Builder $u) => $u->where('name', 'like', '%'.$search.'%'));
            });
        }

        return $query->latest('updated_at')
            ->paginate(min(50, max(10, $request->integer('per_page', 20))))
            ->withQueryString()
            ->through(fn (PaymentEscrow $e) => [
                'id' => $e->id,
                'reference' => $e->reference,
                'quest_id' => $e->quest_id,
                'quest_title' => $e->quest?->title,
                'contract_id' => $e->quest?->reference_code,
                'client' => $e->client?->name,
                'freelancer' => $e->freelancer?->name,
                'amount' => NgnMoney::format((int) $e->amount_minor),
                'status' => $e->status,
                'paystack_reference' => $e->paystack_reference,
                'funded_at' => $e->funded_at?->toIso8601String(),
                'released_at' => $e->released_at?->toIso8601String(),
            ]);
    }

    public function walletList(Request $request): LengthAwarePaginator
    {
        $query = Wallet::query()->with('user:id,name,email,username');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereHas('user', fn (Builder $u) => $u->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->orderByDesc('balance_minor')
            ->paginate(min(50, max(10, $request->integer('per_page', 20))))
            ->withQueryString()
            ->through(fn (Wallet $w) => [
                'id' => $w->id,
                'user_id' => $w->user_id,
                'user_name' => $w->user?->name,
                'user_email' => $w->user?->email,
                'balance' => NgnMoney::format((int) $w->balance_minor),
                'balance_minor' => (int) $w->balance_minor,
                'status' => $w->status,
                'is_locked' => $w->isLocked(),
            ]);
    }

    public function transactionList(Request $request): LengthAwarePaginator
    {
        $query = WalletTransaction::query()->with(['user:id,name,email', 'quest:id,title']);

        foreach (['type', 'direction', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return $query->latest('occurred_at')
            ->paginate(min(100, max(20, $request->integer('per_page', 30))))
            ->withQueryString()
            ->through(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'reference' => $tx->reference,
                'user' => $tx->user?->name,
                'type' => $tx->type,
                'direction' => $tx->direction,
                'amount' => NgnMoney::format((int) $tx->amount_minor),
                'balance_after' => NgnMoney::format((int) $tx->balance_after_minor),
                'status' => $tx->status,
                'quest' => $tx->quest?->title,
                'paystack_reference' => $tx->paystack_reference,
                'occurred_at' => $tx->occurred_at?->toIso8601String(),
            ]);
    }

    public function forceRelease(PaymentEscrow $escrow, User $admin, string $reason): PaymentEscrow
    {
        $quest = $escrow->quest;
        if ($quest === null) {
            throw new \InvalidArgumentException('Quest missing for escrow.');
        }

        return $this->escrowPayments->releaseEscrowToWallet($quest, $admin, $reason);
    }

    public function forceRefund(PaymentEscrow $escrow, User $admin, string $reason): PaymentEscrow
    {
        $quest = $escrow->quest;
        if ($quest === null) {
            throw new \InvalidArgumentException('Quest missing for escrow.');
        }

        return $this->escrowPayments->refundEscrow($quest, $admin, $reason);
    }

    public function lockWallet(User $target, User $admin, string $reason): Wallet
    {
        return $this->wallets->lockWallet($target, $admin, $reason);
    }

    public function unlockWallet(User $target): Wallet
    {
        return $this->wallets->unlockWallet($target);
    }
}
