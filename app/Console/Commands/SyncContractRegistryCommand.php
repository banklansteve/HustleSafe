<?php

namespace App\Console\Commands;

use App\Services\Contracts\ContractRegistrySyncService;
use Illuminate\Console\Command;

class SyncContractRegistryCommand extends Command
{
    protected $signature = 'contracts:sync-registry';

    protected $description = 'Backfill quest_contracts rows for awarded engagements missing from the contract registry';

    public function handle(ContractRegistrySyncService $sync): int
    {
        $result = $sync->syncMissing();

        $this->info(sprintf(
            'Contract registry sync complete: %d created, %d reconciled, %d skipped.',
            $result['created'],
            $result['reconciled'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
