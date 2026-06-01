<?php

namespace App\Jobs;

use App\Models\QuestContractDeliveryExtension;
use App\Services\Contracts\ContractDeliveryExtensionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDeliveryExtensionSubmissionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $extensionId) {}

    public function handle(ContractDeliveryExtensionService $service): void
    {
        $extension = QuestContractDeliveryExtension::query()->find($this->extensionId);
        if ($extension === null) {
            return;
        }

        $service->finalizeSubmission($extension);
    }
}
