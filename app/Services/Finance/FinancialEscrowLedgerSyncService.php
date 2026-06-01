<?php

namespace App\Services\Finance;

use App\Models\LedgerJournalBatch;
use App\Models\PaymentEscrow;

final class FinancialEscrowLedgerSyncService
{
    public function __construct(
        private readonly FinancialLedgerBridgeService $bridge,
    ) {}

    public function syncIfMissing(PaymentEscrow $escrow): void
    {
        if ($escrow->funded_at === null) {
            return;
        }

        $reference = (string) ($escrow->paystack_reference ?: $escrow->reference);

        if (! $this->hasBatch('ledger:escrow-funded:'.$escrow->id)) {
            $this->bridge->onEscrowFunded($escrow->fresh(), $reference);
        }

        $releasedMinor = (int) $escrow->released_minor;
        if ($releasedMinor > 0 && ! $this->hasBatch('ledger:escrow-released:'.$escrow->id.':'.$releasedMinor)) {
            $this->bridge->onEscrowReleased($escrow->fresh(), $releasedMinor, 'sync_backfill', null);
        }

        $refundedMinor = (int) $escrow->refunded_minor;
        if ($refundedMinor > 0 && ! $this->hasBatch('ledger:escrow-refund:'.$escrow->id.':'.$refundedMinor)) {
            $this->bridge->onEscrowRefunded($escrow->fresh(), $refundedMinor, 'Ledger sync refund');
        }
    }

    private function hasBatch(string $idempotencyKey): bool
    {
        return LedgerJournalBatch::query()->where('idempotency_key', $idempotencyKey)->exists();
    }
}
