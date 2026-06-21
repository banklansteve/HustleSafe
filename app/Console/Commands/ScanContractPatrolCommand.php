<?php

namespace App\Console\Commands;

use App\Services\Admin\ContractManagement\ContractPatrolAnomalyService;
use Illuminate\Console\Command;

class ScanContractPatrolCommand extends Command
{
    protected $signature = 'contracts:patrol-scan';

    protected $description = 'Scan active contracts for patrol anomalies and upsert contract_patrol_flags';

    public function handle(ContractPatrolAnomalyService $service): int
    {
        $created = $service->scanAll();
        $this->info("Contract patrol scan complete. {$created} new flag(s) created.");

        return self::SUCCESS;
    }
}
