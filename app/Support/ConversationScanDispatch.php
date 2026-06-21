<?php

namespace App\Support;

use App\Models\QuestConversationMessage;
use App\Services\ConversationMonitoring\ConversationMessagePostScanService;
use App\Services\ConversationMonitoring\ConversationMonitoringService;
use App\Models\ProposalClarificationMessage;

class ConversationScanDispatch
{
    public static function questMessage(int $messageId): void
    {
        defer(function () use ($messageId): void {
            $message = QuestConversationMessage::query()->find($messageId);
            if ($message === null) {
                return;
            }

            app(ConversationMonitoringService::class)->processMessage($message);
            app(ConversationMessagePostScanService::class)->deliverQuestMessage($messageId);
        });
    }

    public static function clarificationMessage(int $messageId): void
    {
        defer(function () use ($messageId): void {
            $message = ProposalClarificationMessage::query()->find($messageId);
            if ($message === null) {
                return;
            }

            app(ConversationMonitoringService::class)->processClarificationMessage($message);
            app(ConversationMessagePostScanService::class)->deliverClarificationMessage($messageId);
        });
    }
}
