<?php

namespace App\Services\ConversationMonitoring;

use App\Enums\ConversationFlagCategory;
use App\Models\ConversationMessageFlag;
use App\Models\ConversationMonitoringTerm;
use App\Models\ConversationPolicyWarning;
use App\Models\ConversationSystematicEscalation;
use App\Models\ConversationThreadReview;
use App\Models\ConversationUserHealthScore;
use App\Models\ProposalClarificationMessage;
use App\Models\ProposalClarificationThread;
use App\Models\QuestConversationMessage;
use App\Models\AdminUserSanction;
use App\Models\User;
use App\Notifications\ConversationMonitoringAssignedNotification;
use App\Notifications\ConversationMonitoringSuperAdminEscalationNotification;
use App\Notifications\ConversationPolicyWarningUserNotification;
use App\Models\StaffResponseTemplate;
use App\Services\ConversationMonitoring\ConversationMonitoringAssignmentService;
use App\Support\PlatformSettings;
use App\Services\AdminActivityLogger;
use App\Support\TrustRisk\UserRiskScoreDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationMonitoringAdminService
{
    public function __construct(
        private readonly ConversationMonitoringScanner $scanner,
        private readonly ConversationHealthScoreService $healthScores,
    ) {}

    public function summary(?User $actor = null): array
    {
        $query = ConversationThreadReview::query()
            ->whereIn('status', ['pending', 'assigned', 'awaiting_super_admin'])
            ->where('flag_count', '>', 0);

        return [
            'moderation_queue' => (clone $query)->count(),
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
            ->with([
                'thread.client:id,name,email',
                'thread.freelancer:id,name,email',
                'clarificationThread.client:id,name,email',
                'clarificationThread.freelancer:id,name,email',
                'assignedStaff:id,name,email',
                'quest:id,title,reference_code',
            ])
            ->whereIn('status', ['pending', 'assigned', 'awaiting_super_admin'])
            ->where('flag_count', '>', 0)
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

    public function threadDetail(ConversationThreadReview $review, bool $revealFull = false, ?User $viewer = null): array
    {
        $review->load([
            'thread.client:id,name,email',
            'thread.freelancer:id,name,email',
            'clarificationThread.client:id,name,email',
            'clarificationThread.freelancer:id,name,email',
            'assignedStaff:id,name,email',
            'quest:id,title,reference_code',
        ]);

        if ($review->isFocusedQa()) {
            return $this->clarificationThreadDetail($review, $revealFull, $viewer);
        }

        $flags = $this->flagsForReview($review)
            ->orderBy('flagged_at')
            ->get()
            ->keyBy('quest_conversation_message_id');

        $flaggedMessageIds = $flags->keys()->filter()->all();

        $messages = QuestConversationMessage::query()
            ->with('user:id,name')
            ->where('quest_conversation_thread_id', $review->quest_conversation_thread_id)
            ->orderBy('created_at')
            ->get()
            ->map(function (QuestConversationMessage $m) use ($flags, $revealFull) {
                $flag = $flags->get($m->id);
                $body = $this->adminMessageBody($m->body, $m->body_original, (bool) $m->is_redacted, $m->redaction_label, $revealFull && $flag !== null);

                return [
                    'id' => $m->id,
                    'user' => $m->user?->only(['id', 'name']),
                    'body' => $body,
                    'is_redacted' => (bool) $m->is_redacted,
                    'redaction_label' => $m->redaction_label,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'is_flagged' => $flag !== null,
                    'flags' => $this->formatFlagPayload($flag),
                ];
            });

        return $this->detailPayload($review, $messages->all(), $flaggedMessageIds, $viewer);
    }

    /**
     * @return array<string, mixed>
     */
    private function clarificationThreadDetail(ConversationThreadReview $review, bool $revealFull, ?User $viewer = null): array
    {
        $threadId = (int) $review->proposal_clarification_thread_id;
        $flags = $this->flagsForReview($review)
            ->orderBy('flagged_at')
            ->get()
            ->keyBy('proposal_clarification_message_id');

        $messages = ProposalClarificationMessage::query()
            ->with('author:id,name')
            ->where('thread_id', $threadId)
            ->orderBy('created_at')
            ->get()
            ->map(function (ProposalClarificationMessage $m) use ($flags, $revealFull) {
                $flag = $flags->get($m->id);
                $body = $this->adminMessageBody($m->body, $m->body_original, (bool) $m->is_redacted, $m->redaction_label, $revealFull && $flag !== null);

                return [
                    'id' => $m->id,
                    'user' => $m->author?->only(['id', 'name']),
                    'role' => $m->role,
                    'body' => $body,
                    'is_redacted' => (bool) $m->is_redacted,
                    'redaction_label' => $m->redaction_label,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'is_flagged' => $flag !== null,
                    'flags' => $this->formatFlagPayload($flag),
                ];
            });

        return $this->detailPayload($review, $messages->all(), $flags->keys()->filter()->all(), $viewer);
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
            ->where(function ($query) use ($review): void {
                $this->applyReviewScopeToFlagsQuery($query, $review);
            })
            ->where('status', 'pending')
            ->update(['status' => 'dismissed']);

        $this->audit($staff, 'conversation_monitoring.dismissed', ConversationThreadReview::class, $review->id, [
            'reason' => $reason,
            'quest_title' => $review->quest?->title,
        ]);
    }

    public function warnUser(ConversationThreadReview $review, User $staff, string $note, ?int $targetUserId = null, ?string $templateSlug = null): void
    {
        $offenderId = $targetUserId ?: $this->primaryOffenderId($review);
        if (! $offenderId) {
            throw ValidationException::withMessages(['user' => __('No offending user identified for this review.')]);
        }

        $target = User::query()->findOrFail($offenderId);
        $messageBody = $note;
        $subject = __('Important: HustleSafe messaging policy');

        if ($templateSlug) {
            $template = StaffResponseTemplate::query()->where('slug', $templateSlug)->where('is_active', true)->first();
            if ($template) {
                $rendered = app(\App\Services\Operations\StaffResponseTemplateService::class)->render($template, [
                    'name' => $target->first_name ?: $target->name,
                    'first_name' => $target->first_name ?: Str::before($target->name ?? '', ' ') ?: $target->name,
                    'quest_title' => $review->quest?->title ?? '',
                ]);
                $messageBody = $rendered['body'] ?? $note;
                $subject = $rendered['subject'] ?? $subject;
            }
        }

        $warning = ConversationPolicyWarning::query()->create([
            'user_id' => $target->id,
            'thread_review_id' => $review->id,
            'issued_by' => $staff->id,
            'note' => $messageBody,
        ]);

        $target->notify(new ConversationPolicyWarningUserNotification($subject, $messageBody, (int) $warning->id));

        ConversationMessageFlag::query()
            ->where(function ($query) use ($review): void {
                $this->applyReviewScopeToFlagsQuery($query, $review);
            })
            ->where('status', 'pending')
            ->update(['status' => 'confirmed']);

        $review->update([
            'status' => 'warned',
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
        ]);

        $this->audit($staff, 'conversation_monitoring.user_warned', User::class, $target->id, [
            'target_user_name' => $target->name,
            'target_user_email' => $target->email,
            'quest_title' => $review->quest?->title,
            'template_slug' => $templateSlug,
            'note' => Str::limit($messageBody, 300),
            'thread_review_id' => $review->id,
        ]);
    }

    public function assignToStaff(ConversationThreadReview $review, User $superAdmin, int $staffId): void
    {
        if ($superAdmin->role?->slug !== 'super_admin') {
            throw ValidationException::withMessages(['assign' => __('Only Super Admins can assign reviews.')]);
        }

        $staff = User::query()->where('id', $staffId)->whereHas('role', fn ($q) => $q->where('slug', 'admin'))->firstOrFail();

        $review->update([
            'assigned_staff_id' => $staff->id,
            'status' => 'assigned',
        ]);

        $staff->notify(new ConversationMonitoringAssignedNotification($review->fresh()));

        $this->audit($superAdmin, 'conversation_monitoring.assigned', ConversationThreadReview::class, $review->id, [
            'assigned_staff_id' => $staff->id,
            'assigned_staff_name' => $staff->name,
            'quest_title' => $review->quest?->title,
        ]);
    }

    public function escalateToSuperAdmin(ConversationThreadReview $review, User $staff, string $note): void
    {
        if ($staff->role?->slug === 'super_admin') {
            throw ValidationException::withMessages(['escalate' => __('Super Admins handle escalated cases directly.')]);
        }

        $review->update([
            'status' => 'awaiting_super_admin',
            'super_admin_escalated_at' => now(),
            'super_admin_escalation_by' => $staff->id,
            'super_admin_escalation_note' => $note,
        ]);

        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
            ->each(fn (User $superAdmin) => $superAdmin->notify(
                new ConversationMonitoringSuperAdminEscalationNotification($review->fresh(), $staff, $note),
            ));

        $this->audit($staff, 'conversation_monitoring.escalated', ConversationThreadReview::class, $review->id, [
            'note' => $note,
            'quest_title' => $review->quest?->title,
        ]);
    }

    public function suspendUser(User $actor, User $target, ConversationThreadReview $review, ?string $note = null): void
    {
        $this->guardSanctionThreshold($target, 'suspend', $actor);
        $weeks = PlatformSettings::conversationMonitoringSanctions()['suspend_duration_weeks'];
        $endsAt = now()->addWeeks($weeks);

        DB::transaction(function () use ($actor, $target, $review, $note, $endsAt): void {
            AdminUserSanction::query()->create([
                'user_id' => $target->id,
                'admin_user_id' => $actor->id,
                'type' => 'suspension',
                'reason_code' => 'policy_violation',
                'notes' => $note ?: 'Conversation monitoring suspension',
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);
            $target->forceFill(['suspended_at' => now()])->save();
            $review->update(['status' => 'resolved', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        });

        $this->audit($actor, 'conversation_monitoring.user_suspended', User::class, $target->id, [
            'target_user_name' => $target->name,
            'target_user_email' => $target->email,
            'note' => $note,
            'quest_title' => $review->quest?->title,
            'thread_review_id' => $review->id,
        ]);
    }

    public function banUser(User $superAdmin, User $target, ConversationThreadReview $review, ?string $note = null): void
    {
        if ($superAdmin->role?->slug !== 'super_admin') {
            throw ValidationException::withMessages(['ban' => __('Only Super Admins can permanently ban users.')]);
        }

        $this->guardSanctionThreshold($target, 'ban', $superAdmin);

        DB::transaction(function () use ($superAdmin, $target, $review, $note): void {
            AdminUserSanction::query()->create([
                'user_id' => $target->id,
                'admin_user_id' => $superAdmin->id,
                'type' => 'ban',
                'reason_code' => 'policy_violation',
                'notes' => $note ?: 'Conversation monitoring permanent ban',
                'starts_at' => now(),
            ]);
            $target->forceFill([
                'banned_at' => now(),
                'ban_reason' => $note ?: 'Repeated conversation policy violations',
            ])->save();
            $review->update(['status' => 'resolved', 'reviewed_by' => $superAdmin->id, 'reviewed_at' => now()]);
        });

        $this->audit($superAdmin, 'conversation_monitoring.user_banned', User::class, $target->id, [
            'target_user_name' => $target->name,
            'target_user_email' => $target->email,
            'note' => $note,
            'quest_title' => $review->quest?->title,
            'thread_review_id' => $review->id,
        ]);
    }

    public function flagForRiskUpdate(ConversationThreadReview $review): void
    {
        [$clientId, $freelancerId] = $this->reviewPartyIds($review);
        if ($clientId === null || $freelancerId === null) {
            return;
        }

        UserRiskScoreDispatcher::dispatchMany([$clientId, $freelancerId]);
        $this->healthScores->recalculateForUser($clientId);
        $this->healthScores->recalculateForUser($freelancerId);
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

        $this->audit($superAdmin, 'conversation_monitoring.systematic_resolved', ConversationSystematicEscalation::class, $escalation->id, [
            'note' => $note,
            'target_user_id' => $escalation->user_id,
        ]);
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
        $clarificationThread = $review->clarificationThread;
        $categories = collect($review->trigger_categories ?? [])->map(
            fn ($c) => ConversationFlagCategory::tryFrom($c)?->label() ?? $c,
        )->all();

        $client = $thread?->client ?? $clarificationThread?->client;
        $freelancer = $thread?->freelancer ?? $clarificationThread?->freelancer;

        return [
            'id' => $review->id,
            'thread_id' => $review->quest_conversation_thread_id,
            'clarification_thread_id' => $review->proposal_clarification_thread_id,
            'source' => $review->isFocusedQa() ? 'focused_qa' : 'quest_messages',
            'source_label' => $review->isFocusedQa() ? 'Focused Q&A' : 'Quest messages',
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
            'client' => $client?->only(['id', 'name', 'email']),
            'freelancer' => $freelancer?->only(['id', 'name', 'email']),
            'assigned_staff' => $review->assignedStaff?->only(['id', 'name', 'email']),
            'super_admin_escalated_at' => $review->super_admin_escalated_at?->toIso8601String(),
            'super_admin_escalation_note' => $review->super_admin_escalation_note,
            'in_risk_queue_hint' => $this->partiesInRiskQueue($client?->id, $freelancer?->id),
        ];
    }

    /**
     * @return list<string>
     */
    private function partiesInRiskQueue(?int $clientId, ?int $freelancerId): array
    {
        if (! Schema::hasTable('conversation_user_health_scores')) {
            return [];
        }

        $hints = [];
        foreach (array_filter([$clientId, $freelancerId]) as $uid) {
            $health = ConversationUserHealthScore::query()->where('user_id', $uid)->first();
            if ($health && $health->health_score < $this->healthScores->healthThreshold()) {
                $hints[] = "User #{$uid} conversation health {$health->health_score}";
            }
        }

        return $hints;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function reviewPartyIds(ConversationThreadReview $review): array
    {
        if ($review->isFocusedQa()) {
            $thread = $review->clarificationThread;
        } else {
            $thread = $review->thread;
        }

        $clientId = $thread?->client_id ? (int) $thread->client_id : null;
        $freelancerId = $thread?->freelancer_id ? (int) $thread->freelancer_id : null;

        return [$clientId, $freelancerId];
    }

    private function flagsForReview(ConversationThreadReview $review): \Illuminate\Database\Eloquent\Builder
    {
        return ConversationMessageFlag::query()->where(function ($query) use ($review): void {
            $this->applyReviewScopeToFlagsQuery($query, $review);
        });
    }

    private function applyReviewScopeToFlagsQuery(\Illuminate\Database\Eloquent\Builder $query, ConversationThreadReview $review): void
    {
        if ($review->isFocusedQa()) {
            $query->where('proposal_clarification_thread_id', $review->proposal_clarification_thread_id);

            return;
        }

        $query->where('quest_conversation_thread_id', $review->quest_conversation_thread_id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatFlagPayload(?ConversationMessageFlag $flag): array
    {
        if (! $flag) {
            return [];
        }

        return [[
            'category' => $flag->trigger_category?->value ?? $flag->trigger_category,
            'category_label' => ConversationFlagCategory::tryFrom($flag->trigger_category?->value ?? (string) $flag->trigger_category)?->label(),
            'pattern' => $flag->matched_pattern_redacted,
            'confidence' => (float) $flag->confidence,
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<int|string>  $flaggedMessageIds
     * @return array<string, mixed>
     */
    private function detailPayload(ConversationThreadReview $review, array $messages, array $flaggedMessageIds, ?User $viewer): array
    {
        return [
            'review' => $this->reviewRow($review),
            'messages' => $messages,
            'flagged_message_ids' => $flaggedMessageIds,
            'party_actions' => $this->partyActionsForReview($review, $viewer),
            'warning_templates' => $this->warningTemplates(),
            'assignable_staff' => $viewer?->role?->slug === 'super_admin' ? $this->assignableStaffAdmins() : [],
            'sanction_thresholds' => PlatformSettings::conversationMonitoringSanctions(),
            'can_dismiss' => true,
            'can_resolve_systematic' => false,
        ];
    }

    private function adminMessageBody(?string $body, ?string $original, bool $isRedacted, ?string $label, bool $revealFull): string
    {
        if ($revealFull && $original) {
            return $original;
        }

        if ($isRedacted) {
            return $label ?: ($body ?: 'REDACTED — POLICY VIOLATION');
        }

        return $this->scanner->redactForDisplay((string) $body);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function partyActionsForReview(ConversationThreadReview $review, ?User $viewer = null): array
    {
        $thresholds = PlatformSettings::conversationMonitoringSanctions();
        $isSuperAdmin = $viewer?->role?->slug === 'super_admin';
        $out = [];

        $review->loadMissing([
            'thread.client:id,name,email',
            'thread.freelancer:id,name,email',
            'clarificationThread.client:id,name,email',
            'clarificationThread.freelancer:id,name,email',
        ]);

        $parties = [
            ['label' => 'Client', 'user' => $review->thread?->client ?? $review->clarificationThread?->client],
            ['label' => 'Freelancer', 'user' => $review->thread?->freelancer ?? $review->clarificationThread?->freelancer],
        ];

        if ($review->thread && ! $parties[0]['user'] && $review->thread->client_id) {
            $parties[0]['user'] = User::query()->find($review->thread->client_id);
        }
        if ($review->thread && ! $parties[1]['user'] && $review->thread->freelancer_id) {
            $parties[1]['user'] = User::query()->find($review->thread->freelancer_id);
        }

        foreach ($parties as $party) {
            $user = $party['user'];
            if (! $user) {
                continue;
            }
            $flagCount = $this->flagCountForUser((int) $user->id);
            $out[] = [
                'label' => $party['label'],
                'user' => $user->only(['id', 'name', 'email']),
                'flag_count' => $flagCount,
                'can_warn' => true,
                'can_suspend' => $flagCount >= $thresholds['suspend_threshold'],
                'can_ban' => $isSuperAdmin && $flagCount >= $thresholds['ban_threshold'],
                'can_escalate_ban' => ! $isSuperAdmin && $flagCount >= $thresholds['ban_threshold'],
            ];
        }

        if ($out === []) {
            $offenderId = $this->primaryOffenderId($review);
            if ($offenderId) {
                $user = User::query()->find($offenderId);
                if ($user) {
                    $flagCount = $this->flagCountForUser((int) $user->id);
                    $out[] = [
                        'label' => 'Flagged sender',
                        'user' => $user->only(['id', 'name', 'email']),
                        'flag_count' => $flagCount,
                        'can_warn' => true,
                        'can_suspend' => $flagCount >= $thresholds['suspend_threshold'],
                        'can_ban' => $isSuperAdmin && $flagCount >= $thresholds['ban_threshold'],
                        'can_escalate_ban' => ! $isSuperAdmin && $flagCount >= $thresholds['ban_threshold'],
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array{id: int, slug: string, title: string, subject: string}>
     */
    private function warningTemplates(): array
    {
        if (! Schema::hasTable('staff_response_templates')) {
            return [];
        }

        return StaffResponseTemplate::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('situation_key', 'off_platform_payment_flagged')
                    ->orWhere('slug', 'like', 'conversation-%')
                    ->orWhere('slug', 'off-platform-payment-warning');
            })
            ->orderBy('title')
            ->get(['id', 'slug', 'title', 'subject'])
            ->map(fn (StaffResponseTemplate $t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'title' => $t->title,
                'subject' => $t->subject,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function assignableStaffAdmins(): array
    {
        $moderators = app(ConversationMonitoringAssignmentService::class)->eligibleModerators();

        if ($moderators->isNotEmpty()) {
            return $moderators
                ->map(fn (User $u) => $u->only(['id', 'name', 'email']))
                ->all();
        }

        return User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => $u->only(['id', 'name', 'email']))
            ->all();
    }

    private function flagCountForUser(int $userId): int
    {
        return ConversationMessageFlag::query()
            ->where('sender_user_id', $userId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
    }

    private function primaryOffenderId(ConversationThreadReview $review): ?int
    {
        $flag = $this->flagsForReview($review)->orderByDesc('flagged_at')->first();

        return $flag?->sender_user_id ? (int) $flag->sender_user_id : null;
    }

    private function guardSanctionThreshold(User $target, string $action, User $actor): void
    {
        $thresholds = PlatformSettings::conversationMonitoringSanctions();
        $count = $this->flagCountForUser((int) $target->id);
        $required = $action === 'ban' ? $thresholds['ban_threshold'] : $thresholds['suspend_threshold'];

        if ($count < $required) {
            throw ValidationException::withMessages([
                'user' => __('User needs at least :n conversation flags before :action.', ['n' => $required, 'action' => $action]),
            ]);
        }

        if (in_array($target->role?->slug, ['admin', 'super_admin'], true)) {
            throw ValidationException::withMessages(['user' => __('Cannot sanction staff admin accounts.')]);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(User $actor, string $action, ?string $subjectType, ?int $subjectId, array $properties = []): void
    {
        app(AdminActivityLogger::class)->log($actor, $action, $subjectType, $subjectId, $properties);
    }
}
