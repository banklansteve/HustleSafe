<?php

namespace App\Console\Commands;

use App\Enums\DeliveryExtensionStatus;
use App\Models\QuestContractDeliveryExtension;
use App\Services\Contracts\ContractDeliveryExtensionService;
use App\Services\Contracts\DeliveryExtensionPatternDetector;
use Illuminate\Console\Command;

class ProcessContractExtensionDeadlinesCommand extends Command
{
    protected $signature = 'contracts:process-extension-deadlines';

    protected $description = 'Auto-approve client-timeout extensions and expire counter-proposals';

    public function handle(ContractDeliveryExtensionService $extensions): int
    {
        $autoApproved = 0;
        $expiredCounters = 0;

        QuestContractDeliveryExtension::query()
            ->where('status', DeliveryExtensionStatus::PendingClient)
            ->where('client_response_deadline_at', '<=', now())
            ->chunkById(50, function ($rows) use ($extensions, &$autoApproved): void {
                foreach ($rows as $extension) {
                    $extensions->autoApproveClientTimeout($extension);
                    $autoApproved++;
                }
            });

        QuestContractDeliveryExtension::query()
            ->where('status', DeliveryExtensionStatus::CounterProposed)
            ->where('counter_response_deadline_at', '<=', now())
            ->chunkById(50, function ($rows) use ($extensions, &$expiredCounters): void {
                foreach ($rows as $extension) {
                    $extensions->expireCounterProposal($extension);
                    $expiredCounters++;
                }
            });

        $this->info("Auto-approved: {$autoApproved}, counter-proposals expired: {$expiredCounters}");

        return self::SUCCESS;
    }
}
