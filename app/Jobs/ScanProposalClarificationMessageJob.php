<?php

namespace App\Jobs;

use App\Models\ProposalClarificationMessage;
use App\Services\ConversationMonitoring\ConversationMessagePostScanService;
use App\Services\ConversationMonitoring\ConversationMonitoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanProposalClarificationMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $messageId) {}

    public function handle(
        ConversationMonitoringService $monitoring,
        ConversationMessagePostScanService $delivery,
    ): void {
        $message = ProposalClarificationMessage::query()->find($this->messageId);
        if ($message === null) {
            return;
        }

        $monitoring->processClarificationMessage($message);
        $delivery->deliverClarificationMessage($this->messageId);
    }
}
