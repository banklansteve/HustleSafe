<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\QuestContract;
use App\Services\Contracts\ContractLifecycleService;
use Illuminate\Console\Command;

class ProcessContractEscrowExpiryCommand extends Command
{
    protected $signature = 'contracts:expire-pending-escrow';

    protected $description = 'Cancel contracts whose escrow funding window has expired';

    public function handle(ContractLifecycleService $lifecycle): int
    {
        QuestContract::query()
            ->where('status', ContractStatus::PendingEscrow)
            ->whereNotNull('escrow_expires_at')
            ->where('escrow_expires_at', '<', now())
            ->orderBy('id')
            ->each(function (QuestContract $contract) use ($lifecycle): void {
                $lifecycle->cancelExpiredPendingEscrow(
                    $contract,
                    __('Escrow was not funded within the 48-hour contract window.')
                );
            });

        return self::SUCCESS;
    }
}
