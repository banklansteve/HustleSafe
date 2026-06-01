<?php

namespace App\Console\Commands;

use App\Models\PaymentEscrow;
use App\Services\Finance\FinancialLedgerBridgeService;
use Illuminate\Console\Command;

class BackfillFinancialLedgerCommand extends Command
{
    protected $signature = 'financial:backfill-ledger {--dry-run : Preview without posting}';

    protected $description = 'Backfill double-entry ledger and escrow records from existing payment escrows';

    public function handle(FinancialLedgerBridgeService $bridge): int
    {
        $escrows = PaymentEscrow::query()
            ->whereNotNull('funded_at')
            ->orderBy('id')
            ->get();

        $this->info('Found '.$escrows->count().' funded escrows.');

        foreach ($escrows as $escrow) {
            if ($this->option('dry-run')) {
                $this->line("Would backfill escrow {$escrow->reference}");
                continue;
            }

            $reference = (string) ($escrow->paystack_reference ?: $escrow->reference);

            $bridge->onEscrowFunded($escrow->fresh(), $reference);

            if ((int) $escrow->released_minor > 0) {
                $bridge->onEscrowReleased(
                    $escrow->fresh(),
                    (int) $escrow->released_minor,
                    'backfill',
                    null,
                );
            }

            if ((int) $escrow->refunded_minor > 0) {
                $bridge->onEscrowRefunded($escrow->fresh(), (int) $escrow->refunded_minor, 'Backfill refund');
            }
        }

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
