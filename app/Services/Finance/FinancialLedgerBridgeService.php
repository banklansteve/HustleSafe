<?php

namespace App\Services\Finance;

use App\Enums\FinancialEscrowRecordStatus;
use App\Enums\LedgerAccount;
use App\Enums\LedgerEventType;
use App\Models\LedgerJournalBatch;
use App\Models\PaymentEscrow;
use App\Models\QuestContract;
use App\Models\WalletWithdrawal;
use App\Support\NgnMoney;

final class FinancialLedgerBridgeService
{
    public function __construct(
        private readonly DoubleEntryLedgerService $ledger,
        private readonly FinancialEscrowRecordService $escrowRecords,
    ) {}

    public function onEscrowFunded(PaymentEscrow $escrow, string $paystackReference): void
    {
        $escrow->loadMissing(['quest', 'client', 'freelancer']);
        $amount = (int) $escrow->amount_minor;
        if ($amount <= 0) {
            return;
        }

        $context = $this->escrowContext($escrow, $paystackReference, $escrow->funded_at ?? now());

        $this->ledger->postBalancedBatch(
            LedgerEventType::EscrowFunded,
            'ledger:escrow-funded:'.$escrow->id,
            [
                ['account' => LedgerAccount::PaymentGatewaySuspense, 'side' => 'debit', 'amount_minor' => $amount],
                ['account' => LedgerAccount::ClientEscrowLiability, 'side' => 'credit', 'amount_minor' => $amount],
            ],
            'EscrowPaymentService::markEscrowFundedFromPaystack',
            $context,
            __('Escrow funded via Paystack'),
        );

        $this->escrowRecords->upsertFromFunding($escrow, $paystackReference);
    }

    public function onEscrowReleased(
        PaymentEscrow $escrow,
        int $grossMinor,
        string $releaseTrigger,
        ?string $walletCreditReference = null,
    ): void {
        if ($grossMinor <= 0) {
            return;
        }

        $escrow->loadMissing(['quest', 'client', 'freelancer']);
        $breakdown = NgnMoney::escrowReleaseBreakdown($grossMinor);
        $context = $this->escrowContext($escrow, $escrow->paystack_reference, $escrow->released_at ?? now());
        $context['meta'] = [
            'release_trigger' => $releaseTrigger,
            'gross_minor' => $grossMinor,
            ...$breakdown,
        ];

        $lines = [
            ['account' => LedgerAccount::ClientEscrowLiability, 'side' => 'debit', 'amount_minor' => $grossMinor],
            ['account' => LedgerAccount::FreelancerPayable, 'side' => 'credit', 'amount_minor' => $breakdown['freelancer_net_minor']],
        ];

        if ($breakdown['platform_revenue_minor'] > 0) {
            $lines[] = ['account' => LedgerAccount::PlatformFeeRevenue, 'side' => 'credit', 'amount_minor' => $breakdown['platform_revenue_minor']];
        }

        if ($breakdown['vat_minor'] > 0) {
            $lines[] = ['account' => LedgerAccount::VatPayable, 'side' => 'credit', 'amount_minor' => $breakdown['vat_minor']];
        }

        $this->ledger->postBalancedBatch(
            LedgerEventType::EscrowReleased,
            'ledger:escrow-released:'.$escrow->id.':'.$grossMinor,
            $lines,
            'EscrowPaymentService::releaseEscrowToWallet',
            $context,
            __('Escrow released — :trigger', ['trigger' => str_replace('_', ' ', $releaseTrigger)]),
        );

        $this->escrowRecords->appendRelease($escrow, $grossMinor, $releaseTrigger, $walletCreditReference, $breakdown);
    }

    public function onEscrowRefunded(PaymentEscrow $escrow, int $amountMinor, string $reason): void
    {
        if ($amountMinor <= 0) {
            return;
        }

        $escrow->loadMissing(['quest', 'client', 'freelancer']);
        $context = $this->escrowContext($escrow, $escrow->paystack_reference, $escrow->refunded_at ?? now());

        $this->ledger->postBalancedBatch(
            LedgerEventType::DisputeRefund,
            'ledger:escrow-refund:'.$escrow->id.':'.$amountMinor,
            [
                ['account' => LedgerAccount::ClientEscrowLiability, 'side' => 'debit', 'amount_minor' => $amountMinor],
                ['account' => LedgerAccount::RefundPayable, 'side' => 'credit', 'amount_minor' => $amountMinor],
            ],
            'EscrowPaymentService::refundEscrow',
            $context,
            $reason,
        );

        $this->escrowRecords->appendRefund($escrow, $amountMinor);
    }

    public function onWithdrawalInitiated(WalletWithdrawal $withdrawal): void
    {
        $amount = (int) $withdrawal->amount_minor;
        if ($amount <= 0) {
            return;
        }

        $context = [
            'wallet_withdrawal_id' => $withdrawal->id,
            'freelancer_id' => $withdrawal->user_id,
            'paystack_reference' => $withdrawal->paystack_reference,
        ];

        $this->ledger->postBalancedBatch(
            LedgerEventType::WithdrawalInitiated,
            'ledger:withdrawal-init:'.$withdrawal->id,
            [
                ['account' => LedgerAccount::FreelancerPayable, 'side' => 'debit', 'amount_minor' => $amount],
                ['account' => LedgerAccount::WithdrawalClearing, 'side' => 'credit', 'amount_minor' => $amount],
            ],
            'WithdrawalService::requestWithdrawal',
            $context,
            __('Withdrawal initiated'),
        );
    }

    public function onWithdrawalConfirmed(WalletWithdrawal $withdrawal): void
    {
        $amount = (int) $withdrawal->amount_minor;
        if ($amount <= 0) {
            return;
        }

        $context = [
            'wallet_withdrawal_id' => $withdrawal->id,
            'freelancer_id' => $withdrawal->user_id,
            'paystack_reference' => $withdrawal->paystack_reference,
        ];

        $this->ledger->postBalancedBatch(
            LedgerEventType::WithdrawalConfirmed,
            'ledger:withdrawal-confirmed:'.$withdrawal->id,
            [
                ['account' => LedgerAccount::WithdrawalClearing, 'side' => 'debit', 'amount_minor' => $amount],
                ['account' => LedgerAccount::PaymentGatewaySuspense, 'side' => 'credit', 'amount_minor' => $amount],
            ],
            'EscrowPaymentService::handleTransferSuccess',
            $context,
            __('Withdrawal confirmed via Paystack'),
        );
    }

    public function onWithdrawalReversed(WalletWithdrawal $withdrawal): void
    {
        $amount = (int) $withdrawal->amount_minor;
        if ($amount <= 0) {
            return;
        }

        $initBatch = LedgerJournalBatch::query()
            ->where('idempotency_key', 'ledger:withdrawal-init:'.$withdrawal->id)
            ->first();

        if ($initBatch !== null) {
            $this->ledger->reverseBatch($initBatch, __('Withdrawal failed — funds returned to wallet'), 'EscrowPaymentService::handleTransferFailed');

            return;
        }

        $context = [
            'wallet_withdrawal_id' => $withdrawal->id,
            'freelancer_id' => $withdrawal->user_id,
            'paystack_reference' => $withdrawal->paystack_reference,
        ];

        $this->ledger->postBalancedBatch(
            LedgerEventType::WithdrawalReversed,
            'ledger:withdrawal-reversed:'.$withdrawal->id,
            [
                ['account' => LedgerAccount::WithdrawalClearing, 'side' => 'debit', 'amount_minor' => $amount],
                ['account' => LedgerAccount::FreelancerPayable, 'side' => 'credit', 'amount_minor' => $amount],
            ],
            'EscrowPaymentService::handleTransferFailed',
            $context,
            __('Withdrawal failed — funds returned to wallet'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function escrowContext(PaymentEscrow $escrow, ?string $paystackReference, \DateTimeInterface|string|null $occurredAt = null): array
    {
        $contract = QuestContract::query()->where('quest_id', $escrow->quest_id)->latest('id')->first();
        $at = $occurredAt instanceof \DateTimeInterface
            ? $occurredAt
            : (filled($occurredAt) ? \Illuminate\Support\Carbon::parse($occurredAt) : now());

        return [
            'payment_escrow_id' => $escrow->id,
            'quest_id' => $escrow->quest_id,
            'quest_contract_id' => $contract?->id,
            'client_id' => $escrow->client_id,
            'freelancer_id' => $escrow->freelancer_id,
            'paystack_reference' => $paystackReference ?? $escrow->paystack_reference,
            'occurred_at' => $at,
        ];
    }
}
