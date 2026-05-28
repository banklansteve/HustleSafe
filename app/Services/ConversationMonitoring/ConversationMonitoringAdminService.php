<?php

namespace App\Services\ConversationMonitoring;

use App\Enums\ConversationFlagCategory;
use App\Models\ConversationMessageFlag;
use App\Models\ConversationMonitoringTerm;
use App\Models\ConversationPolicyWarning;
use App\Models\ConversationSystematicEscalation;
use App\Models\ConversationThreadReview;
use App\Models\ConversationUserHealthScore;
use App\Models\QuestConversationMessage;
use App\Models\User;
use App\Support\TrustRisk\UserRiskScoreDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConversationMonitoringAdminService
{
    public function __construct(
        private readonly ConversationMonitoringScanner $scanner,
        private readonly ConversationHealthScoreService $healthScores,
    ) {}

    public function summary(): array
    {
        return [
            'moderation_queue' => ConversationThreadReview::query()->whereIn('status', ['pending', 'escalated'])->count(),
            'systematic_queue' => ConversationSystematicEscalation::query()->where('status', 'open')->count(),
            'flags_today' => ConversationMessageFlag::query()->whereDate('flagged_at', today())->count(),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function moderationQueue(Request $request): array
    {
        $perPage = max(10, min(50, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));

        $query = ConversationThreadReview::query()
            ->with(['thread.client:id,name,email', 'thread.freelancer:id,name,email', 'quest:id,title,reference_code'])
            ->whereIn('status', ['pending', 'escalated'])
            ->orderByDesc('priority')
            ->orderByDesc('last_flagged_at');

        if ($request->filled('category')) {
            $cat = (string) $request->query('category');
            $query->whereJsonContains('trigger_categories', $cat);
        }

        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn (ConversationThreadReview $r) => $this->reviewRow($r));

        return [
            'items' => $items->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
            'categories' => collect(ConversationFlagCategory::cases())
                ->map(fn (ConversationFlagCategory $c) => ['value' => $c->value, 'label' => $c->label()])
                ->all(),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function systematicQueue(Request $request): array
    {
        $perPage = max(10, min(50, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        $query = ConversationSystematicEscalation::query()
            ->with('user:id,name,email')
            ->where('status', 'open')
            ->orderByDesc('detected_at');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn (ConversationSystematicEscalation $e) => [
            'id' => $e->id,
            'user' => $e->user?->only(['id', 'name', 'email']),
            'trigger_category' => $e->trigger_category,
            'trigger_label' => ConversationFlagCategory::tryFrom($e->trigger_category)?->label() ?? $e->trigger_category,
            'instance_count' => $e->instance_count,
            'distinct_counterparties' => $e->distinct_counterparties,
            'distinct_contracts' => $e->distinct_contracts,
            'detected_at' => $e->detected_at?->toIso8601String(),
            'timeline' => $e->timeline ?? [],
        ]);

        return [
            'items' => $items->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ];
    }

    public function threadDetail(ConversationThreadReview $review, bool $revealFull = false): array
    {
        $review->load(['thread.client:id,name,email', 'thread.freelancer:id,name,email', 'quest:id,title,reference_code']);

        $flags = ConversationMessageFlag::query()
            ->where('quest_conversation_thread_id', $review->quest_conversation_thread_id)
            ->orderBy('flagged_at')
            ->get()
            ->keyBy('quest_conversation_message_id');

        $flaggedMessageIds = $flags->keys()->all();

        $messages = QuestConversationMessage::query()
            ->with('user:id,name')
            ->where('quest_conversation_thread_id', $review->quest_conversation_thread_id)
            ->orderBy('created_at')
            ->get()
            ->map(function (QuestConversationMessage $m) use ($flags, $revealFull) {
                $flag = $flags->get($m->id);
                $body = $revealFull ? (string) $m->body : app(ConversationMonitoringScanner::class)->redactForDisplay((string) $m->body);

                return [
                    'id' => $m->id,
                    'user' => $m->user?->only(['id', 'name']),
                    'body' => $body,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'is_flagged' => $flag !== null,
                    'flags' => $flag ? [[
                        'category' => $flag->trigger_category?->value ?? $flag->trigger_category,
                        'category_label' => ConversationFlagCategory::tryFrom($flag->trigger_category?->value ?? (string) $flag->trigger_category)?->label(),
                        'pattern' => $flag->matched_pattern_redacted,
                        'confidence' => (float) $flag->confidence,
                    ]] : [],
                ];
            });

        return [
            'review' => $this->reviewRow($review),
            'messages' => $messages->all(),
            'flagged_message_ids' => $flaggedMessageIds,
            'can_dismiss' => true,
            'can_resolve_systematic' => false,
        ];
    }

    public function systematicDetail(ConversationSystematicEscalation $escalation): array
    {
        $escalation->load('user:id,name,email');

        return [
            'escalation' => [
                'id' => $escalation->id,
                'user' => $escalation->user?->only(['id', 'name', 'email']),
                'trigger_category' => $escalation->trigger_category,
                'trigger_label' => ConversationFlagCategory::tryFrom($escalation->trigger_category)?->label(),
                'instance_count' => $escalation->instance_count,
                'distinct_counterparties' => $escalation->distinct_counterparties,
                'distinct_contracts' => $escalation->distinct_contracts,
                'timeline' => $escalation->timeline ?? [],
                'detected_at' => $escalation->detected_at?->toIso8601String(),
            ],
            'can_dismiss' => false,
            'can_resolve_systematic' => true,
        ];
    }

    public function dismiss(ConversationThreadReview $review, User $staff, string $reason): void
    {
        $review->update([
            'status' => 'dismissed',
            'dismiss_reason' => $reason,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
        ]);

        ConversationMessageFlag::query()
            ->where('quest_conversation_thread_id', $review->quest_conversation_thread_id)
            ->where('status', 'pending')
            ->update(['status' => 'dismissed']);
    }

    public function warnUser(ConversationThreadReview $review, User $staff, string $note): void
    {
        $thread = $review->thread;
        if (! $thread) {
            return;
        }

        foreach ([$thread->client_id, $thread->freelancer_id] as $uid) {
            ConversationPolicyWarning::query()->create([
                'user_id' => $uid,
                'thread_review_id' => $review->id,
                'issued_by' => $staff->id,
                'note' => $note,
            ]);
        }

        ConversationMessageFlag::query()
            ->where('quest_conversation_thread_id', $review->quest_conversation_thread_id)
            ->where('status', 'pending')
            ->update(['status' => 'confirmed']);

        $review->update([
            'status' => 'warned',
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
        ]);
    }

    public function escalate(ConversationThreadReview $review, User $staff): void
    {
        $review->update([
            'status' => 'escalated',
            'escalated_to_admin_id' => $staff->id,
            'escalated_at' => now(),
        ]);
    }

    public function flagForRiskUpdate(ConversationThreadReview $review): void
    {
        $thread = $review->thread;
        if (! $thread) {
            return;
        }

        UserRiskScoreDispatcher::dispatchMany([(int) $thread->client_id, (int) $thread->freelancer_id]);
        $this->healthScores->recalculateForUser((int) $thread->client_id);
        $this->healthScores->recalculateForUser((int) $thread->freelancer_id);
    }

    public function resolveSystematic(ConversationSystematicEscalation $escalation, User $superAdmin, string $note): void
    {
        if ($superAdmin->role?->slug !== 'super_admin') {
            throw ValidationException::withMessages(['resolution' => 'Only Super Admins can resolve systematic escalations.']);
        }

        $escalation->update([
            'status' => 'resolved',
            'resolution_note' => $note,
            'resolved_by' => $superAdmin->id,
            'resolved_at' => now(),
        ]);

        UserRiskScoreDispatcher::dispatch((int) $escalation->user_id);
    }

    public function attemptDismissSystematic(ConversationSystematicEscalation $escalation, User $staff): void
    {
        if ($staff->role?->slug !== 'super_admin') {
            throw ValidationException::withMessages([
                'action' => 'Systematic escalations cannot be dismissed by staff. Super Admin resolution is required.',
            ]);
        }
    }

    /**
     * @return array{terms: list<array<string, mixed>>}
     */
    public function termsPayload(): array
    {
        $terms = ConversationMonitoringTerm::query()
            ->orderBy('term_type')
            ->orderBy('pattern')
            ->get()
            ->map(fn (ConversationMonitoringTerm $t) => [
                'id' => $t->id,
                'term_type' => $t->term_type,
                'pattern' => $t->pattern,
                'is_wildcard' => $t->is_wildcard,
                'is_active' => $t->is_active,
                'locale_hint' => $t->locale_hint,
            ]);

        return [
            'terms' => $terms->all(),
            'health_threshold' => $this->healthScores->healthThreshold(),
        ];
    }

    public function storeTerm(User $actor, array $data): ConversationMonitoringTerm
    {
        return ConversationMonitoringTerm::query()->create([
            'term_type' => $data['term_type'],
            'pattern' => $data['pattern'],
            'is_wildcard' => (bool) ($data['is_wildcard'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'locale_hint' => $data['locale_hint'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    public function updateTerm(ConversationMonitoringTerm $term, array $data): ConversationMonitoringTerm
    {
        $term->update([
            'pattern' => $data['pattern'] ?? $term->pattern,
            'is_wildcard' => $data['is_wildcard'] ?? $term->is_wildcard,
            'is_active' => $data['is_active'] ?? $term->is_active,
            'locale_hint' => $data['locale_hint'] ?? $term->locale_hint,
        ]);

        return $term->fresh();
    }

    public function deleteTerm(ConversationMonitoringTerm $term): void
    {
        $term->delete();
    }

    private function reviewRow(ConversationThreadReview $review): array
    {
        $thread = $review->thread;
        $categories = collect($review->trigger_categories ?? [])->map(
            fn ($c) => ConversationFlagCategory::tryFrom($c)?->label() ?? $c,
        )->all();

        return [
            'id' => $review->id,
            'thread_id' => $review->quest_conversation_thread_id,
            'status' => $review->status,
            'priority' => $review->priority,
            'flag_count' => $review->flag_count,
            'categories' => $categories,
            'first_flagged_at' => $review->first_flagged_at?->toIso8601String(),
            'last_flagged_at' => $review->last_flagged_at?->toIso8601String(),
            'quest' => $review->quest ? [
                'id' => $review->quest->id,
                'title' => $review->quest->title,
                'reference' => $review->quest->reference_code,
            ] : null,
            'client' => $thread?->client?->only(['id', 'name', 'email']),
            'freelancer' => $thread?->freelancer?->only(['id', 'name', 'email']),
            'in_risk_queue_hint' => $this->partiesInRiskQueue($thread),
        ];
    }

    /**
     * @return list<string>
     */
    private function partiesInRiskQueue(?\App\Models\QuestConversationThread $thread): array
    {
        if (! $thread || ! Schema::hasTable('conversation_user_health_scores')) {
            return [];
        }

        $hints = [];
        foreach ([$thread->client_id, $thread->freelancer_id] as $uid) {
            $health = ConversationUserHealthScore::query()->where('user_id', $uid)->first();
            if ($health && $health->health_score < $this->healthScores->healthThreshold()) {
                $hints[] = "User #{$uid} conversation health {$health->health_score}";
            }
        }

        return $hints;
    }
}
