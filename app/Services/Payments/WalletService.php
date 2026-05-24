<?php

namespace App\Services\Payments;

use App\Models\AdminFinancialLedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\NgnMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function ensureWallet(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'currency' => (string) config('payment.currency', 'NGN'),
                'balance_minor' => 0,
                'pending_balance_minor' => 0,
                'status' => 'active',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function credit(
        User $user,
        int $amountMinor,
        string $type,
        ?string $idempotencyKey = null,
        array $meta = [],
        ?int $escrowId = null,
        ?int $questId = null,
        ?int $feeMinor = 0,
        ?string $paystackReference = null,
        ?string $description = null,
        ?User $admin = null,
    ): WalletTransaction {
        return $this->postEntry($user, $amountMinor, 'credit', $type, $idempotencyKey, $meta, $escrowId, $questId, $feeMinor, $paystackReference, $description, $admin);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function debit(
        User $user,
        int $amountMinor,
        string $type,
        ?string $idempotencyKey = null,
        array $meta = [],
        ?int $escrowId = null,
        ?int $questId = null,
        ?int $feeMinor = 0,
        ?string $paystackReference = null,
        ?string $description = null,
        ?User $admin = null,
    ): WalletTransaction {
        return $this->postEntry($user, $amountMinor, 'debit', $type, $idempotencyKey, $meta, $escrowId, $questId, $feeMinor, $paystackReference, $description, $admin);
    }

    /**
     * Audit-only entry that does not change wallet balance (e.g. escrow hold on client side).
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordAudit(
        User $user,
        int $amountMinor,
        string $type,
        ?string $idempotencyKey = null,
        array $meta = [],
        ?int $escrowId = null,
        ?int $questId = null,
        ?string $paystackReference = null,
        ?string $description = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amountMinor, $type, $idempotencyKey, $meta, $escrowId, $questId, $paystackReference, $description): WalletTransaction {
            if ($idempotencyKey !== null) {
                $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            $wallet = $this->ensureWallet($user);
            $this->assertWalletActive($wallet);

            return WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => $type,
                'direction' => 'neutral',
                'amount_minor' => $amountMinor,
                'fee_minor' => 0,
                'balance_after_minor' => (int) $wallet->balance_minor,
                'status' => 'completed',
                'paystack_reference' => $paystackReference,
                'idempotency_key' => $idempotencyKey,
                'escrow_id' => $escrowId,
                'quest_id' => $questId,
                'description' => $description,
                'meta' => $meta,
            ]);
        });
    }

    public function lockWallet(User $user, User $admin, string $reason): Wallet
    {
        $wallet = $this->ensureWallet($user);
        $wallet->update([
            'status' => 'locked',
            'locked_at' => now(),
            'lock_reason' => $reason,
            'locked_by_user_id' => $admin->id,
        ]);

        return $wallet->fresh();
    }

    public function unlockWallet(User $user): Wallet
    {
        $wallet = $this->ensureWallet($user);
        $wallet->update([
            'status' => 'active',
            'locked_at' => null,
            'lock_reason' => null,
            'locked_by_user_id' => null,
        ]);

        return $wallet->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function walletPayload(User $user): array
    {
        $wallet = $this->ensureWallet($user);

        return [
            'id' => $wallet->id,
            'currency' => $wallet->currency,
            'balance' => NgnMoney::format((int) $wallet->balance_minor),
            'balance_minor' => (int) $wallet->balance_minor,
            'pending_balance_minor' => (int) $wallet->pending_balance_minor,
            'status' => $wallet->status,
            'is_locked' => $wallet->isLocked(),
            'lock_reason' => $wallet->lock_reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function postEntry(
        User $user,
        int $amountMinor,
        string $direction,
        string $type,
        ?string $idempotencyKey,
        array $meta,
        ?int $escrowId,
        ?int $questId,
        int $feeMinor,
        ?string $paystackReference,
        ?string $description,
        ?User $admin,
    ): WalletTransaction {
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => [__('Amount must be greater than zero.')]]);
        }

        return DB::transaction(function () use ($user, $amountMinor, $direction, $type, $idempotencyKey, $meta, $escrowId, $questId, $feeMinor, $paystackReference, $description, $admin): WalletTransaction {
            if ($idempotencyKey !== null) {
                $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?? $this->ensureWallet($user)->fresh();
            $this->assertWalletActive($wallet);

            $balance = (int) $wallet->balance_minor;
            if ($direction === 'debit' && $balance < $amountMinor) {
                throw ValidationException::withMessages(['balance' => [__('Insufficient wallet balance.')]]);
            }

            $balanceAfter = $direction === 'credit'
                ? $balance + $amountMinor
                : $balance - $amountMinor;

            $wallet->update(['balance_minor' => $balanceAfter]);

            $tx = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => $type,
                'direction' => $direction,
                'amount_minor' => $amountMinor,
                'fee_minor' => $feeMinor,
                'balance_after_minor' => $balanceAfter,
                'status' => 'completed',
                'paystack_reference' => $paystackReference,
                'idempotency_key' => $idempotencyKey,
                'escrow_id' => $escrowId,
                'quest_id' => $questId,
                'admin_user_id' => $admin?->id,
                'description' => $description,
                'meta' => $meta,
            ]);

            if ($questId !== null && in_array($type, ['escrow_release', 'withdrawal', 'fee'], true)) {
                $this->mirrorAdminLedger($tx, $questId, $user, $admin);
            }

            return $tx;
        });
    }

    protected function mirrorAdminLedger(WalletTransaction $tx, int $questId, User $user, ?User $admin): void
    {
        $quest = \App\Models\Quest::query()->find($questId);
        if ($quest === null) {
            return;
        }

        AdminFinancialLedgerEntry::query()->create([
            'quest_id' => $quest->id,
            'quest_offer_id' => $quest->accepted_quest_offer_id,
            'client_id' => $quest->client_id,
            'freelancer_id' => $quest->freelancer_id,
            'admin_user_id' => $admin?->id,
            'type' => match ($tx->type) {
                'escrow_release' => 'milestone_release',
                'withdrawal' => 'payout',
                'fee' => 'platform_fee',
                default => 'admin_adjustment',
            },
            'direction' => $tx->direction === 'credit' ? 'credit' : 'debit',
            'source' => $admin ? 'admin' : 'system',
            'description' => $tx->description ?? ucfirst(str_replace('_', ' ', $tx->type)),
            'gross_amount_minor' => (int) $tx->amount_minor,
            'fee_amount_minor' => (int) $tx->fee_minor,
            'net_amount_minor' => $tx->direction === 'credit'
                ? (int) $tx->amount_minor - (int) $tx->fee_minor
                : -((int) $tx->amount_minor),
            'balance_after_minor' => (int) $tx->balance_after_minor,
            'paystack_reference' => $tx->paystack_reference,
            'meta' => ['wallet_transaction_id' => $tx->id],
            'occurred_at' => $tx->occurred_at ?? now(),
        ]);
    }

    protected function assertWalletActive(Wallet $wallet): void
    {
        if ($wallet->isLocked()) {
            throw ValidationException::withMessages(['wallet' => [__('Your wallet is locked. Contact support for assistance.')]]);
        }
    }
}
