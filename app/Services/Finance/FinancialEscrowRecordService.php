<?php

namespace App\Services\Finance;

use App\Enums\FinancialEscrowRecordStatus;
use App\Models\FinancialEscrowRecord;
use App\Models\PaymentEscrow;
use App\Models\QuestContract;
use App\Support\NgnMoney;
use App\Support\PlatformSettings;

final class FinancialEscrowRecordService
{
    public function upsertFromFunding(PaymentEscrow $escrow, string $paystackReference): FinancialEscrowRecord
    {
        $escrow->loadMissing(['quest.questCategory', 'client', 'freelancer']);
        $quest = $escrow->quest;
        $contract = QuestContract::query()->where('quest_id', $escrow->quest_id)->latest('id')->first();
        $gross = (int) $escrow->amount_minor;
        $feePercent = PlatformSettings::platformFeePercent();
        $breakdown = NgnMoney::escrowReleaseBreakdown($gross, $feePercent);

        return FinancialEscrowRecord::query()->updateOrCreate(
            ['payment_escrow_id' => $escrow->id],
            [
                'escrow_reference' => $escrow->reference,
                'quest_id' => $escrow->quest_id,
                'quest_contract_id' => $contract?->id,
                'contract_reference' => $contract?->reference_code,
                'quest_title' => (string) ($quest?->title ?? 'Quest #'.$escrow->quest_id),
                'quest_category_id' => $quest?->quest_category_id,
                'client_id' => $escrow->client_id,
                'client_name' => $escrow->client?->name ?? 'Client',
                'freelancer_id' => $escrow->freelancer_id,
                'freelancer_name' => $escrow->freelancer?->name ?? 'Freelancer',
                'gross_contract_value_minor' => $gross,
                'total_funded_minor' => $gross,
                'platform_fee_percent' => $feePercent,
                'platform_fee_minor' => $breakdown['platform_fee_minor'],
                'vat_percent' => PlatformSettings::vatPercent(),
                'vat_minor' => $breakdown['vat_minor'],
                'freelancer_net_minor' => $breakdown['freelancer_net_minor'],
                'gateway_name' => 'paystack',
                'paystack_reference' => $paystackReference,
                'funded_at' => $escrow->funded_at ?? now(),
                'status' => $this->mapEscrowStatus($escrow),
            ],
        );
    }

    /**
     * @param  array{platform_fee_minor: int, vat_minor: int, platform_revenue_minor: int, freelancer_net_minor: int}  $breakdown
     */
    public function appendRelease(
        PaymentEscrow $escrow,
        int $grossMinor,
        string $releaseTrigger,
        ?string $walletCreditReference,
        array $breakdown,
    ): void {
        $record = FinancialEscrowRecord::query()->where('payment_escrow_id', $escrow->id)->first();
        if ($record === null) {
            $record = $this->upsertFromFunding($escrow, (string) $escrow->paystack_reference);
        }

        $releasedTotal = (int) $escrow->released_minor;
        $grossTotal = (int) $escrow->amount_minor;
        $isPartial = $releasedTotal > 0 && $releasedTotal < $grossTotal;

        $feeFields = $isPartial
            ? [
                'platform_fee_minor' => (int) $record->platform_fee_minor + $breakdown['platform_fee_minor'],
                'vat_minor' => (int) $record->vat_minor + $breakdown['vat_minor'],
                'freelancer_net_minor' => (int) $record->freelancer_net_minor + $breakdown['freelancer_net_minor'],
            ]
            : (function () use ($releasedTotal): array {
                $total = NgnMoney::escrowReleaseBreakdown($releasedTotal);

                return [
                    'platform_fee_minor' => $total['platform_fee_minor'],
                    'vat_minor' => $total['vat_minor'],
                    'freelancer_net_minor' => $total['freelancer_net_minor'],
                ];
            })();

        $record->update([
            ...$feeFields,
            'status' => $isPartial
                ? FinancialEscrowRecordStatus::PartiallyReleased->value
                : FinancialEscrowRecordStatus::Released->value,
            'release_trigger_type' => $releaseTrigger,
            'released_at' => $escrow->released_at ?? now(),
            'wallet_credit_reference' => $walletCreditReference,
            'fee_recognised_at' => $escrow->released_at ?? now(),
        ]);
    }

    public function appendRefund(PaymentEscrow $escrow, int $amountMinor): void
    {
        $record = FinancialEscrowRecord::query()->where('payment_escrow_id', $escrow->id)->first();
        if ($record === null) {
            $record = $this->upsertFromFunding($escrow, (string) $escrow->paystack_reference);
        }

        $refundedTotal = (int) $escrow->refunded_minor;
        $isPartial = $refundedTotal > 0 && $refundedTotal < (int) $escrow->amount_minor;

        $record->update([
            'status' => $isPartial
                ? FinancialEscrowRecordStatus::PartiallyReleased->value
                : FinancialEscrowRecordStatus::Refunded->value,
            'refunded_at' => $escrow->refunded_at ?? now(),
        ]);
    }

    public function markDisputed(PaymentEscrow $escrow): void
    {
        FinancialEscrowRecord::query()
            ->where('payment_escrow_id', $escrow->id)
            ->update(['status' => FinancialEscrowRecordStatus::Disputed->value]);
    }

    private function mapEscrowStatus(PaymentEscrow $escrow): string
    {
        return match ($escrow->status) {
            'released' => FinancialEscrowRecordStatus::Released->value,
            'refunded' => FinancialEscrowRecordStatus::Refunded->value,
            'partially_released' => FinancialEscrowRecordStatus::PartiallyReleased->value,
            default => FinancialEscrowRecordStatus::Held->value,
        };
    }
}
