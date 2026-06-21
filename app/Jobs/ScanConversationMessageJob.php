<?php

namespace App\Jobs;

use App\Models\QuestConversationMessage;
use App\Services\ConversationMonitoring\ConversationMessagePostScanService;
use App\Services\ConversationMonitoring\ConversationMonitoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanConversationMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $messageId) {}

    public function handle(
        ConversationMonitoringService $monitoring,
        ConversationMessagePostScanService $delivery,
    ): void {
        $message = QuestConversationMessage::query()->find($this->messageId);
        if ($message === null) {
            return;
        }

        $monitoring->processMessage($message);
        $delivery->deliverQuestMessage($this->messageId);
    }
}
