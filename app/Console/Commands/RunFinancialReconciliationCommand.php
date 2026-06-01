<?php

namespace App\Console\Commands;

use App\Services\Finance\FinancialReconciliationService;
use Illuminate\Console\Command;

class RunFinancialReconciliationCommand extends Command
{
    protected $signature = 'financial:reconcile {--force : Run even if another run is in progress}';

    protected $description = 'Run hourly financial reconciliation (gateway, ledger balance, escrow position)';

    public function handle(FinancialReconciliationService $reconciliation): int
    {
        $this->info('Starting financial reconciliation…');
        $run = $reconciliation->run();

        $this->line('Status: '.$run->status);
        $this->line('Records processed: '.$run->records_processed);
        $this->line('Exceptions: '.$run->exceptions_found);

        if ($run->error_message) {
            $this->error($run->error_message);
        }

        return $run->status === 'passed' ? self::SUCCESS : self::FAILURE;
    }
}
