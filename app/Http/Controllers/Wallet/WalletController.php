<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\ResolveBankAccountRequest;
use App\Http\Requests\Wallet\StoreBankAccountRequest;
use App\Http\Requests\Wallet\StoreWithdrawalRequest;
use App\Models\WalletTransaction;
use App\Services\Payments\PaystackClient;
use App\Services\Payments\WalletService;
use App\Services\Payments\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly WithdrawalService $withdrawals,
        private readonly PaystackClient $paystack,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = $this->wallets->ensureWallet($user);

        $transactions = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->latest('occurred_at')
            ->limit(50)
            ->get()
            ->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'reference' => $tx->reference,
                'type' => $tx->type,
                'direction' => $tx->direction,
                'amount' => \App\Support\NgnMoney::format((int) $tx->amount_minor),
                'amount_minor' => (int) $tx->amount_minor,
                'balance_after' => \App\Support\NgnMoney::format((int) $tx->balance_after_minor),
                'status' => $tx->status,
                'description' => $tx->description,
                'occurred_at' => $tx->occurred_at?->toIso8601String(),
            ]);

        $bankAccounts = $user->walletBankAccounts()
            ->orderByDesc('is_default')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'bank_name' => $a->bank_name,
                'account_name' => $a->account_name,
                'account_number_masked' => $a->maskedAccountNumber(),
                'is_default' => $a->is_default,
            ]);

        return Inertia::render('Wallet/Index', [
            'wallet' => $this->wallets->walletPayload($user),
            'transactions' => $transactions,
            'bankAccounts' => $bankAccounts,
            'banks' => $this->withdrawals->nigerianBanks(),
            'paystackPublicKey' => $this->paystack->publicKey(),
            'paystackEnabled' => $this->paystack->enabled(),
            'withdrawalMin' => \App\Support\NgnMoney::format((int) config('payment.withdrawal.min_amount_minor', 100000)),
            'withdrawalFee' => \App\Support\NgnMoney::format((int) config('payment.withdrawal.fee_minor', 5000)),
        ]);
    }

    public function resolveAccount(ResolveBankAccountRequest $request): JsonResponse
    {
        $data = $this->withdrawals->resolveAccount(
            $request->validated('account_number'),
            $request->validated('bank_code'),
        );

        return response()->json([
            'account_name' => (string) ($data['account_name'] ?? ''),
            'account_number' => (string) ($data['account_number'] ?? $request->validated('account_number')),
        ]);
    }

    public function storeBankAccount(StoreBankAccountRequest $request): RedirectResponse
    {
        $this->withdrawals->saveBankAccount(
            $request->user(),
            $request->validated('bank_code'),
            $request->validated('bank_name'),
            $request->validated('account_number'),
            $request->validated('account_name'),
            true,
        );

        return back()->with('success', __('Bank account saved.'));
    }

    public function withdraw(StoreWithdrawalRequest $request): RedirectResponse
    {
        $amountMinor = \App\Support\NgnMoney::toMinor($request->validated('amount'));
        $this->withdrawals->requestWithdrawal(
            $request->user(),
            $amountMinor,
            $request->validated('bank_account_id'),
        );

        return back()->with('success', __('Withdrawal submitted. Funds will arrive in your bank account once processed.'));
    }
}
