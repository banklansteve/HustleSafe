<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\WalletBankAccount;
use App\Models\WalletWithdrawal;
use App\Support\NgnMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(
        private readonly PaystackClient $paystack,
        private readonly WalletService $wallets,
    ) {}

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function nigerianBanks(): array
    {
        if (! $this->paystack->enabled()) {
            return $this->fallbackBanks();
        }

        try {
            return collect($this->paystack->listBanks())
                ->map(fn (array $bank) => [
                    'code' => (string) ($bank['code'] ?? ''),
                    'name' => (string) ($bank['name'] ?? ''),
                ])
                ->filter(fn (array $b) => $b['code'] !== '' && $b['name'] !== '')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return $this->fallbackBanks();
        }
    }

    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        if (! $this->paystack->enabled()) {
            return [
                'account_number' => $accountNumber,
                'account_name' => 'Test Account',
                'bank_code' => $bankCode,
            ];
        }

        $result = $this->paystack->resolveAccountNumber($accountNumber, $bankCode);

        return $result['data'] ?? [];
    }

    public function saveBankAccount(User $user, string $bankCode, string $bankName, string $accountNumber, string $accountName, bool $setDefault = true): WalletBankAccount
    {
        return DB::transaction(function () use ($user, $bankCode, $bankName, $accountNumber, $accountName, $setDefault): WalletBankAccount {
            if ($setDefault) {
                WalletBankAccount::query()->where('user_id', $user->id)->update(['is_default' => false]);
            }

            $account = WalletBankAccount::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'bank_code' => $bankCode,
                    'account_number' => $accountNumber,
                ],
                [
                    'bank_name' => $bankName,
                    'account_name' => $accountName,
                    'is_default' => $setDefault,
                    'status' => 'active',
                ],
            );

            if ($this->paystack->enabled() && blank($account->paystack_recipient_code)) {
                $recipient = $this->paystack->createTransferRecipient([
                    'type' => 'nuban',
                    'name' => $accountName,
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                    'currency' => 'NGN',
                ]);
                $account->update([
                    'paystack_recipient_code' => data_get($recipient, 'data.recipient_code'),
                ]);
            }

            return $account->fresh();
        });
    }

    public function requestWithdrawal(User $user, int $amountMinor, ?int $bankAccountId = null): WalletWithdrawal
    {
        $min = (int) config('payment.withdrawal.min_amount_minor', 100000);
        $feeMinor = (int) config('payment.withdrawal.fee_minor', 5000);

        if ($amountMinor < $min) {
            throw ValidationException::withMessages([
                'amount' => [__('Minimum withdrawal is :amount.', ['amount' => NgnMoney::format($min)])],
            ]);
        }

        $wallet = $this->wallets->ensureWallet($user);
        if ($wallet->isLocked()) {
            throw ValidationException::withMessages(['wallet' => [__('Your wallet is locked.')]]);
        }

        $totalDebit = $amountMinor + $feeMinor;
        if ((int) $wallet->balance_minor < $totalDebit) {
            throw ValidationException::withMessages(['balance' => [__('Insufficient balance for withdrawal and fees.')]]);
        }

        $bank = $bankAccountId
            ? WalletBankAccount::query()->where('user_id', $user->id)->where('id', $bankAccountId)->first()
            : WalletBankAccount::query()->where('user_id', $user->id)->where('is_default', true)->first();

        if ($bank === null) {
            throw ValidationException::withMessages(['bank' => [__('Add a Nigerian bank account before withdrawing.')]]);
        }

        return DB::transaction(function () use ($user, $wallet, $amountMinor, $feeMinor, $totalDebit, $bank): WalletWithdrawal {
            $reference = 'WDR-'.Str::upper(Str::random(12));

            $withdrawal = WalletWithdrawal::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'wallet_bank_account_id' => $bank->id,
                'amount_minor' => $amountMinor,
                'fee_minor' => $feeMinor,
                'status' => 'pending',
                'paystack_reference' => $reference,
            ]);

            $this->wallets->debit(
                $user,
                $amountMinor,
                'withdrawal',
                'withdrawal:debit:'.$withdrawal->id,
                ['withdrawal_reference' => $withdrawal->reference],
                null,
                null,
                0,
                $reference,
                __('Withdrawal to bank'),
            );

            if ($feeMinor > 0) {
                $this->wallets->debit(
                    $user,
                    $feeMinor,
                    'fee',
                    'withdrawal:fee:'.$withdrawal->id,
                    ['withdrawal_reference' => $withdrawal->reference],
                    null,
                    null,
                    0,
                    null,
                    __('Withdrawal processing fee'),
                );
            }

            if ($this->paystack->enabled()) {
                if (blank($bank->paystack_recipient_code)) {
                    $this->saveBankAccount($user, $bank->bank_code, $bank->bank_name, $bank->account_number, $bank->account_name, $bank->is_default);
                    $bank = $bank->fresh();
                }

                $transfer = $this->paystack->initiateTransfer([
                    'source' => 'balance',
                    'amount' => $amountMinor,
                    'reference' => $reference,
                    'recipient' => $bank->paystack_recipient_code,
                    'reason' => 'HustleSafe wallet withdrawal',
                ]);

                $withdrawal->update([
                    'paystack_transfer_code' => data_get($transfer, 'data.transfer_code'),
                    'status' => 'processing',
                ]);
            } else {
                $withdrawal->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                    'meta' => ['stub' => true],
                ]);
            }

            return $withdrawal->fresh(['bankAccount']);
        });
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    protected function fallbackBanks(): array
    {
        return [
            ['code' => '058', 'name' => 'Guaranty Trust Bank'],
            ['code' => '011', 'name' => 'First Bank of Nigeria'],
            ['code' => '033', 'name' => 'United Bank for Africa'],
            ['code' => '057', 'name' => 'Zenith Bank'],
            ['code' => '044', 'name' => 'Access Bank'],
        ];
    }
}
