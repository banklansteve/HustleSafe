<?php

namespace App\Services\ConversationMonitoring;

use App\Models\ConversationMessageFlag;
use App\Models\ConversationThreadReview;
use App\Models\QuestConversationMessage;
use App\Models\QuestOffer;
use Illuminate\Support\Facades\Schema;

class ConversationMonitoringService
{
    public function __construct(
        private readonly ConversationMonitoringScanner $scanner,
        private readonly ConversationHealthScoreService $healthScores,
    ) {}

    public function processMessage(QuestConversationMessage $message): void
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return;
        }

        $message->loadMissing('thread.quest');
        $body = trim((string) $message->body);
        if ($body === '') {
            return;
        }

        $hits = $this->scanner->scan($body);
        if ($hits === []) {
            return;
        }

        $thread = $message->thread;
        $quest = $thread?->quest;
        $offer = $quest
            ? QuestOffer::query()
                ->where('quest_id', $quest->id)
                ->where('freelancer_id', $thread->freelancer_id)
                ->whereNotNull('accepted_at')
                ->first()
            : null;

        foreach ($hits as $hit) {
            ConversationMessageFlag::query()->create([
                'quest_conversation_thread_id' => $message->quest_conversation_thread_id,
                'quest_conversation_message_id' => $message->id,
                'sender_user_id' => $message->user_id,
                'quest_id' => $quest?->id,
                'quest_offer_id' => $offer?->id,
                'trigger_category' => $hit['category']->value,
                'matched_pattern_redacted' => $hit['pattern_redacted'],
                'confidence' => $hit['confidence'],
                'status' => 'pending',
                'flagged_at' => now(),
            ]);
        }

        $categories = collect($hits)->map(fn ($h) => $h['category']->value)->unique()->values()->all();
        $this->syncThreadReview($message, $categories);
        $this->healthScores->recalculateForUser((int) $message->user_id);
    }

    /**
     * @param  list<string>  $categories
     */
    private function syncThreadReview(QuestConversationMessage $message, array $categories): void
    {
        $threadId = (int) $message->quest_conversation_thread_id;
        $pendingCount = ConversationMessageFlag::query()
            ->where('quest_conversation_thread_id', $threadId)
            ->where('status', 'pending')
            ->count();

        $review = ConversationThreadReview::query()->firstOrNew([
            'quest_conversation_thread_id' => $threadId,
        ]);

        if ($review->exists && in_array($review->status, ['dismissed', 'resolved'], true)) {
            $review->status = 'pending';
        } elseif (! $review->exists) {
            $review->status = 'pending';
        }

        $existingCategories = $review->trigger_categories ?? [];
        $mergedCategories = array_values(array_unique(array_merge($existingCategories, $categories)));

        $priority = collect($categories)->contains(fn ($c) => in_array($c, ['off_platform_payment', 'external_contact'], true))
            ? 'high'
            : 'normal';

        $review->fill([
            'quest_id' => $message->thread?->quest_id,
            'priority' => $priority,
            'trigger_categories' => $mergedCategories,
            'flag_count' => $pendingCount,
            'first_flagged_at' => $review->first_flagged_at ?? now(),
            'last_flagged_at' => now(),
        ]);
        $review->save();
    }
}
