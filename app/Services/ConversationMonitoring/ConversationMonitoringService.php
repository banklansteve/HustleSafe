<?php

namespace App\Services\ConversationMonitoring;

use App\Models\ConversationMessageFlag;
use App\Models\ConversationThreadReview;
use App\Models\ProposalClarificationMessage;
use App\Models\ProposalClarificationThread;
use App\Models\QuestConversationMessage;
use App\Models\QuestOffer;
use Illuminate\Support\Facades\Schema;

class ConversationMonitoringService
{
    public function __construct(
        private readonly ConversationMonitoringScanner $scanner,
        private readonly ConversationHealthScoreService $healthScores,
        private readonly ConversationMessageRedactionService $redaction,
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
        $questId = $quest?->id ?? $thread?->quest_id;
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
                'detection_reasoning' => $hit['reasoning'] ?? null,
                'pattern_score' => $hit['pattern_score'] ?? null,
                'context_score' => $hit['context_score'] ?? null,
                'status' => 'pending',
                'flagged_at' => now(),
            ]);
        }

        $this->redaction->applyToConversationMessage($message, $hits);

        $categories = collect($hits)->map(fn ($h) => $h['category']->value)->unique()->values()->all();
        $this->syncConversationThreadReview($message->quest_conversation_thread_id, $questId, $categories);
        $this->healthScores->recalculateForUser((int) $message->user_id);
    }

    public function processClarificationMessage(ProposalClarificationMessage $message): void
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return;
        }

        $message->loadMissing('thread.quest', 'thread.offer');
        $body = trim((string) $message->body);
        if ($body === '') {
            return;
        }

        $hits = $this->scanner->scan($body);
        if ($hits === []) {
            return;
        }

        $thread = $message->thread;
        if (! $thread) {
            return;
        }

        if ($this->isPlatformClarificationPrompt($message)) {
            return;
        }

        foreach ($hits as $hit) {
            ConversationMessageFlag::query()->create([
                'quest_conversation_thread_id' => null,
                'quest_conversation_message_id' => null,
                'proposal_clarification_thread_id' => $thread->id,
                'proposal_clarification_message_id' => $message->id,
                'sender_user_id' => $message->author_user_id,
                'quest_id' => $thread->quest_id,
                'quest_offer_id' => $thread->quest_offer_id,
                'trigger_category' => $hit['category']->value,
                'matched_pattern_redacted' => $hit['pattern_redacted'],
                'confidence' => $hit['confidence'],
                'detection_reasoning' => $hit['reasoning'] ?? null,
                'pattern_score' => $hit['pattern_score'] ?? null,
                'context_score' => $hit['context_score'] ?? null,
                'status' => 'pending',
                'flagged_at' => now(),
            ]);
        }

        $this->redaction->applyToClarificationMessage($message, $hits);

        $categories = collect($hits)->map(fn ($h) => $h['category']->value)->unique()->values()->all();
        $this->syncClarificationThreadReview($thread, $categories);
        $this->healthScores->recalculateForUser((int) $message->author_user_id);
    }

    /**
     * @param  list<string>  $categories
     */
    private function syncConversationThreadReview(int $threadId, ?int $questId, array $categories): void
    {
        $pendingCount = ConversationMessageFlag::query()
            ->where('quest_conversation_thread_id', $threadId)
            ->where('status', 'pending')
            ->count();

        $review = ConversationThreadReview::query()->firstOrNew([
            'quest_conversation_thread_id' => $threadId,
        ]);

        $existed = $review->exists;
        $this->fillThreadReview($review, $questId, $categories, $pendingCount);
        $review->save();

        app(ConversationMonitoringAssignmentService::class)->afterReviewSynced($review, $existed);
    }

    /**
     * @param  list<string>  $categories
     */
    private function syncClarificationThreadReview(ProposalClarificationThread $thread, array $categories): void
    {
        $pendingCount = ConversationMessageFlag::query()
            ->where('proposal_clarification_thread_id', $thread->id)
            ->where('status', 'pending')
            ->count();

        $review = ConversationThreadReview::query()->firstOrNew([
            'proposal_clarification_thread_id' => $thread->id,
        ]);

        $existed = $review->exists;
        $this->fillThreadReview($review, $thread->quest_id, $categories, $pendingCount);
        $review->save();

        app(ConversationMonitoringAssignmentService::class)->afterReviewSynced($review, $existed);
    }

    /**
     * @param  list<string>  $categories
     */
    private function fillThreadReview(
        ConversationThreadReview $review,
        ?int $questId,
        array $categories,
        int $pendingCount,
    ): void {
        if ($review->exists && in_array($review->status, ['dismissed', 'resolved', 'warned'], true)) {
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
            'quest_id' => $questId ?? $review->quest_id,
            'priority' => $priority,
            'trigger_categories' => $mergedCategories,
            'flag_count' => $pendingCount,
            'first_flagged_at' => $review->first_flagged_at ?? now(),
            'last_flagged_at' => now(),
        ]);
    }

    /**
     * Platform-authored clarification prompts are scanned but never flagged or redacted.
     */
    private function isPlatformClarificationPrompt(ProposalClarificationMessage $message): bool
    {
        if ($message->role !== 'client') {
            return false;
        }

        $promptKey = trim((string) ($message->prompt_key ?? ''));

        return $promptKey !== ''
            && $promptKey !== 'custom'
            && ! str_starts_with($promptKey, 'reply:');
    }
}
