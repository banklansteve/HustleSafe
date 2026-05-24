<?php

namespace App\Services\Admin;

use App\Enums\QuestStatus;
use App\Enums\AdminQuestStatus;
use App\Models\AdminActivityLog;
use App\Models\AdminQuestFlag;
use App\Models\AdminQuestNote;
use App\Models\AdminQuestNotice;
use App\Models\FeaturedQuestListing;
use App\Models\QuestCategory;
use App\Models\Quest;
use App\Models\QuestFile;
use App\Models\QuestOffer;
use App\Models\State;
use App\Models\User;
use App\Notifications\AdminQuestModerationNotification;
use App\Services\AdminActivityLogger;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuestManagementEngineService
{
    private ?bool $questFlagsTableExists = null;

    public function __construct(
        private readonly AdminActivityLogger $activity,
    ) {}

    public function dashboard(Request $request): array
    {
        return [
            'summary' => $this->summary(),
            'quests' => $this->listing($request),
            'filters' => $this->filters($request),
            'options' => $this->options(),
        ];
    }

    public function summary(): array
    {
        return Cache::remember('admin.quest-engine.summary', now()->addSeconds(20), fn () => $this->freshSummary());
    }

    private function freshSummary(): array
    {
        $live = Quest::query()->where('status', QuestStatus::Open)->count();
        $adminCounts = Quest::query()
            ->selectRaw('admin_status, count(*) as aggregate')
            ->groupBy('admin_status')
            ->pluck('aggregate', 'admin_status');
        $flagged = $this->questFlagsAvailable()
            ? AdminQuestFlag::query()->where('status', 'open')->distinct('quest_id')->count('quest_id')
            : 0;
        $stale = Quest::query()
            ->where('status', QuestStatus::Open)
            ->where('created_at', '<=', now()->subHours(72))
            ->where(function (Builder $query): void {
                $query->whereNull('offers_count')->orWhere('offers_count', 0);
            })
            ->count();
        $disputes = Quest::query()
            ->where('dispute_opened', true)
            ->orWhere('status', QuestStatus::InDispute)
            ->count();

        return [
            ['key' => 'live', 'label' => 'Total live Quests', 'value' => $live, 'filter' => ['quick' => 'open']],
            ['key' => 'admin_flagged', 'label' => 'Flagged', 'value' => (int) ($adminCounts[AdminQuestStatus::Flagged->value] ?? 0), 'filter' => ['admin_status' => AdminQuestStatus::Flagged->value]],
            ['key' => 'under_review', 'label' => 'Under Review', 'value' => (int) ($adminCounts[AdminQuestStatus::UnderReview->value] ?? 0), 'filter' => ['admin_status' => AdminQuestStatus::UnderReview->value]],
            ['key' => 'referred', 'label' => 'Referred', 'value' => (int) ($adminCounts[AdminQuestStatus::Referred->value] ?? 0), 'filter' => ['admin_status' => AdminQuestStatus::Referred->value]],
            ['key' => 'action_required', 'label' => 'Action Required', 'value' => (int) ($adminCounts[AdminQuestStatus::ActionRequired->value] ?? 0), 'filter' => ['admin_status' => AdminQuestStatus::ActionRequired->value]],
            ['key' => 'suspended', 'label' => 'Suspended', 'value' => (int) ($adminCounts[AdminQuestStatus::Suspended->value] ?? 0), 'filter' => ['admin_status' => AdminQuestStatus::Suspended->value]],
            ['key' => 'risk_today', 'label' => 'Risk signals today', 'value' => $flagged, 'filter' => ['quick' => 'flagged']],
            ['key' => 'stale', 'label' => 'No proposals 72h', 'value' => $stale, 'filter' => ['quick' => 'stale']],
            ['key' => 'disputed', 'label' => 'Active disputes', 'value' => $disputes, 'filter' => ['quick' => 'disputed']],
        ];
    }

    public function listing(Request $request): LengthAwarePaginator
    {
        $flagsAvailable = $this->questFlagsAvailable();
        $relations = [
                'client:id,name,email,avatar_url,verification_tier,created_at',
                'questCategory:id,name,parent_id,icon_color',
                'questCategory.parent:id,name,icon_color',
                'stateModel:id,name',
                'activeFeaturedListing:id,quest_id,tier,expires_at,status',
                'adminStatusChangedBy:id,name,email',
                'adminQuestNotices:id,quest_id,type,visible_to_users',
        ];

        if ($flagsAvailable) {
            $relations[] = 'activeAdminQuestFlags:id,quest_id,type,priority,description,due_at,status,assigned_group,created_at';
        }

        $query = Quest::query()
            ->select([
                'id',
                'client_id',
                'quest_category_id',
                'state_id',
                'uuid',
                'slug',
                'reference_code',
                'title',
                'description',
                'budget_amount_minor',
                'max_offers',
                'offers_count',
                'status',
                'admin_status',
                'admin_status_reason',
                'admin_status_changed_by',
                'admin_status_changed_at',
                'escrow_status',
                'escrow_funded_at',
                'listing_expires_at',
                'created_at',
                'updated_at',
            ])
            ->with($relations);

        $this->applyFilters($query, $request);

        $sort = (string) $request->input('sort', '-created_at');
        match ($sort) {
            'budget' => $query->orderBy('budget_amount_minor'),
            '-budget' => $query->orderByDesc('budget_amount_minor'),
            'proposals' => $query->orderBy('offers_count'),
            '-proposals' => $query->orderByDesc('offers_count'),
            'created_at' => $query->orderBy('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return $query
            ->paginate(min(100, max(25, $request->integer('per_page', 25))))
            ->withQueryString()
            ->through(fn (Quest $quest) => $this->row($quest));
    }

    public function detail(Quest $quest): array
    {
        $quest->loadMissing([
            'client:id,name,email,avatar_url,verification_tier,created_at',
            'freelancer:id,name,email,avatar_url,verification_tier',
            'questCategory.parent',
            'stateModel',
            'localGovernment',
            'files',
            'activeFeaturedListing',
            'acceptedOffer.freelancer:id,name,email,avatar_url,verification_tier',
            'adminStatusChangedBy:id,name,email',
            'adminQuestNotices.creator:id,name,email',
            'adminQuestNotes.admin:id,name,email,avatar_url',
        ]);

        if ($this->questFlagsAvailable()) {
            $quest->loadMissing([
                'activeAdminQuestFlags.creator:id,name,email',
                'activeAdminQuestFlags.assignee:id,name,email',
            ]);
        }

        return [
            'overview' => $this->overview($quest),
            'proposals' => $this->proposalPayload($quest),
            'escrow' => $this->escrowPayload($quest),
            'media' => $this->mediaPayload($quest),
            'activity' => $this->activityPayload($quest),
            'completion_timeline' => app(AdminQuestCompletionEventsService::class)->questTimeline($quest->id),
            'release_controls' => $this->releaseControlsPayload($quest),
            'communications' => $this->communicationsPayload($quest),
            'notices' => $quest->adminQuestNotices->sortByDesc('created_at')->map(fn (AdminQuestNotice $notice) => $this->noticeRow($notice))->values(),
            'notes' => $quest->adminQuestNotes->sortByDesc('is_pinned')->sortByDesc('created_at')->map(fn (AdminQuestNote $note) => $this->noteRow($note))->values(),
            'flags' => $this->questFlagsAvailable() && $quest->relationLoaded('activeAdminQuestFlags')
                ? $quest->activeAdminQuestFlags->map(fn (AdminQuestFlag $flag) => $this->flagRow($flag))->values()
                : collect(),
            'edit_options' => $this->editOptions(),
        ];
    }

    public function changeStatus(Quest $quest, User $admin, array $data, Request $request): Quest
    {
        $from = $quest->status?->value ?? (string) $quest->status;
        $to = (string) $data['status'];

        if (! collect(QuestStatus::cases())->pluck('value')->contains($to)) {
            throw ValidationException::withMessages(['status' => 'Choose a supported quest status.']);
        }

        if ($to === QuestStatus::InProgress->value) {
            $quest->loadMissing('acceptedOffer');
            app(VerificationEngineService::class)->assertCanMoveToInProgress($quest, $quest->acceptedOffer);
        }

        $quest->forceFill(['status' => QuestStatus::from($to)])->save();
        Cache::forget('admin.quest-engine.summary');

        $this->activity->log($admin, 'admin.quest.status_changed', Quest::class, $quest->id, [
            'from' => $from,
            'to' => $to,
            'reason' => $data['reason'] ?? null,
            'note' => $data['note'] ?? null,
            'notify_client' => (bool) ($data['notify_client'] ?? true),
        ], $request);

        return $quest->refresh();
    }

    public function flag(Quest $quest, User $admin, array $data, Request $request): AdminQuestFlag
    {
        if (! $this->questFlagsAvailable()) {
            throw ValidationException::withMessages(['description' => 'Quest flagging is not available until the admin_quest_flags migration has been run.']);
        }

        $flag = AdminQuestFlag::query()->create([
            'quest_id' => $quest->id,
            'created_by_admin_id' => $admin->id,
            'assigned_to_admin_id' => $data['assigned_to_admin_id'] ?? null,
            'assigned_group' => $data['assigned_group'] ?? null,
            'type' => $data['type'],
            'priority' => $data['priority'],
            'description' => $data['description'],
            'due_at' => $data['due_at'] ?? null,
            'status' => 'open',
        ]);
        Cache::forget('admin.quest-engine.summary');

        $this->activity->log($admin, 'admin.quest.flag_created', Quest::class, $quest->id, $flag->only([
            'type', 'priority', 'description', 'assigned_to_admin_id', 'assigned_group', 'due_at',
        ]) + [
            'visibility_impact' => $data['visibility_impact'] ?? 'none',
            'notify_client' => (bool) ($data['notify_client'] ?? false),
        ], $request);

        if ((bool) ($data['notify_client'] ?? false) && $quest->client) {
            $quest->client->notify(new AdminQuestModerationNotification(
                $quest,
                __('HustleSafe review opened on your Quest'),
                $data['description'],
                'quest_admin_flag_created',
            ));
        }

        $impact = $data['visibility_impact'] ?? 'none';
        if ($impact === 'hide_pending_resolution' && mb_strlen((string) $data['description']) < 50) {
            throw ValidationException::withMessages(['description' => 'Suspending a Quest requires at least 50 characters of context.']);
        }
        if (in_array($impact, ['restrict_new_proposals', 'hide_pending_resolution'], true)) {
            app(AdminQuestModerationService::class)->changeStatus($quest, $admin, [
                'admin_status' => $impact === 'hide_pending_resolution'
                    ? AdminQuestStatus::Suspended->value
                    : AdminQuestStatus::Restricted->value,
                'reason' => $data['description'],
                'notify_client' => (bool) ($data['notify_client'] ?? false),
            ], $request);
        } elseif (($quest->admin_status?->value ?? (string) $quest->admin_status) === AdminQuestStatus::Clear->value) {
            app(AdminQuestModerationService::class)->changeStatus($quest, $admin, [
                'admin_status' => AdminQuestStatus::Flagged->value,
                'reason' => $data['description'],
                'notify_client' => (bool) ($data['notify_client'] ?? false),
            ], $request);
        }

        return $flag->load(['creator:id,name,email', 'assignee:id,name,email']);
    }

    public function resolveFlag(AdminQuestFlag $flag, User $admin, array $data, Request $request): AdminQuestFlag
    {
        if (! $this->questFlagsAvailable()) {
            throw ValidationException::withMessages(['resolution_note' => 'Quest flagging is not available until the admin_quest_flags migration has been run.']);
        }

        $flag->forceFill([
            'status' => 'resolved',
            'resolution_outcome' => $data['resolution_outcome'],
            'resolution_note' => $data['resolution_note'],
            'resolved_by_admin_id' => $admin->id,
            'resolved_at' => now(),
        ])->save();
        Cache::forget('admin.quest-engine.summary');

        $this->activity->log($admin, 'admin.quest.flag_resolved', Quest::class, $flag->quest_id, [
            'flag_id' => $flag->id,
            'resolution_outcome' => $flag->resolution_outcome,
            'resolution_note' => $flag->resolution_note,
        ], $request);

        return $flag->load(['creator:id,name,email', 'assignee:id,name,email', 'resolver:id,name,email']);
    }

    public function boost(Quest $quest, User $admin, array $data, Request $request): FeaturedQuestListing
    {
        $hasActiveBoost = FeaturedQuestListing::query()
            ->where('quest_id', $quest->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasActiveBoost) {
            throw ValidationException::withMessages(['tier' => 'This quest already has an active boost package. Extend, upgrade, or remove the existing boost first.']);
        }

        $listing = app(PromotionsGrowthService::class)->grantFeatured([
            'quest_id' => $quest->id,
            'tier' => $data['tier'],
            'duration_days' => (int) $data['duration_days'],
            'starts_at' => $data['starts_at'] ?? null,
            'amount_paid_minor' => (int) ($data['amount_paid_minor'] ?? 0),
            'manual_grant_reason' => trim(($data['grant_reason'] ?? 'Admin boost package').': '.($data['internal_note'] ?? '')),
        ], $admin);
        Cache::forget('admin.quest-engine.summary');

        $this->activity->log($admin, 'admin.quest.boost_granted', Quest::class, $quest->id, [
            'listing_id' => $listing->id,
            'tier' => $listing->tier,
            'duration_days' => (int) $data['duration_days'],
            'starts_at' => $listing->starts_at?->toIso8601String(),
            'expires_at' => $listing->expires_at?->toIso8601String(),
            'grant_type' => $data['grant_type'] ?? null,
            'paid_upgrade' => (bool) ($data['paid_upgrade'] ?? false),
            'payment_method' => $data['payment_method'] ?? null,
            'grant_reason' => $data['grant_reason'] ?? null,
            'internal_note' => $data['internal_note'] ?? null,
        ], $request);

        return $listing;
    }

    public function updateQuest(Quest $quest, User $admin, array $data, Request $request): Quest
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 20) {
            throw ValidationException::withMessages(['reason' => __('Explain the admin edit in at least 20 characters.')]);
        }

        $before = $quest->only([
            'title',
            'description',
            'quest_category_id',
            'budget_amount_minor',
            'max_offers',
            'visibility',
            'project_type',
            'start_timing',
            'scheduled_start_date',
            'estimated_completion_days',
            'due_at',
            'state_id',
            'city',
        ]);

        $updates = [
            'title' => $data['title'],
            'description' => $data['description'],
            'quest_category_id' => $data['quest_category_id'] ?? null,
            'budget_amount_minor' => (int) round(((float) $data['budget_amount']) * 100),
            'max_offers' => $data['max_offers'] ?? null,
            'visibility' => $data['visibility'] ?? null,
            'project_type' => $data['project_type'] ?? null,
            'start_timing' => $data['start_timing'] ?? null,
            'scheduled_start_date' => $data['scheduled_start_date'] ?? null,
            'estimated_completion_days' => $data['estimated_completion_days'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'city' => $data['city'] ?? null,
        ];

        if ($quest->title !== $updates['title']) {
            $updates['slug'] = Str::slug($updates['title']).'-'.Str::lower(Str::random(6));
        }

        $quest->forceFill($updates)->save();
        Cache::forget('admin.quest-engine.summary');

        $quest->refresh();
        $after = $quest->only(array_keys($before));
        $diff = collect($after)
            ->filter(fn ($value, $key) => ($before[$key] ?? null) != $value)
            ->map(fn ($value, $key) => ['before' => $before[$key] ?? null, 'after' => $value])
            ->all();

        $this->activity->log($admin, 'admin.quest.updated', Quest::class, $quest->id, [
            'reason' => $reason,
            'diff' => $diff,
            'notify_client' => (bool) ($data['notify_client'] ?? true),
            'notification_preview' => $data['notification_preview'] ?? null,
        ], $request);

        if ((bool) ($data['notify_client'] ?? true) && $quest->client) {
            $quest->client->notify(new AdminQuestModerationNotification(
                $quest,
                __('Your Quest was updated by HustleSafe'),
                (string) ($data['notification_preview'] ?? $reason),
                'quest_admin_updated',
            ));
        }

        return $quest;
    }

    public function deleteQuest(Quest $quest, User $admin, array $data, Request $request): void
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 30) {
            throw ValidationException::withMessages(['reason' => __('Deleting a Quest requires at least 30 characters of reason.')]);
        }
        if (($data['confirmation_title'] ?? '') !== $quest->title) {
            throw ValidationException::withMessages(['confirmation_title' => __('Type the Quest title exactly to confirm deletion.')]);
        }
        if ($quest->accepted_quest_offer_id || $quest->freelancer_id || in_array($quest->status?->value ?? (string) $quest->status, [QuestStatus::Assigned->value, QuestStatus::InProgress->value], true)) {
            throw ValidationException::withMessages(['confirmation_title' => __('This Quest has an active contract. Resolve the contract before deleting it.')]);
        }

        $quest->loadMissing(['client', 'offers.freelancer']);
        $proposalRecipients = $quest->offers->pluck('freelancer')->filter()->unique('id');
        $reference = $quest->reference_code;
        $title = $quest->title;
        $questId = $quest->id;

        $this->activity->log($admin, 'admin.quest.deleted', Quest::class, $questId, [
            'reference_code' => $reference,
            'title' => $title,
            'reason' => $reason,
            'notify_client' => (bool) ($data['notify_client'] ?? true),
            'proposal_recipients' => $proposalRecipients->count(),
        ], $request);

        if ((bool) ($data['notify_client'] ?? true) && $quest->client) {
            $quest->client->notify(new AdminQuestModerationNotification($quest, __('Your Quest was removed by HustleSafe'), $reason, 'quest_admin_deleted'));
        }
        $proposalRecipients->each(fn (User $user) => $user->notify(new AdminQuestModerationNotification($quest, __('A Quest you proposed for was removed'), $reason, 'quest_admin_deleted_proposal_notice')));

        $quest->delete();
        Cache::forget('admin.quest-engine.summary');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $quick = (string) $request->input('quick', '');
        if (str_starts_with($quick, 'admin:')) {
            $query->where('admin_status', str($quick)->after('admin:')->toString());

            return;
        }

        match ($quick) {
            'open' => $query->where('status', QuestStatus::Open),
            'posted_24h' => $query->where('created_at', '>=', now()->subDay()),
            'flagged' => $this->questFlagsAvailable() ? $query->whereHas('activeAdminQuestFlags') : $query->whereRaw('1 = 0'),
            'stale' => $query->where('status', QuestStatus::Open)->where('created_at', '<=', now()->subHours(48))->where(fn (Builder $q) => $q->whereNull('offers_count')->orWhere('offers_count', 0)),
            'contracts' => $query->whereIn('status', [QuestStatus::Assigned, QuestStatus::InProgress])->whereNotNull('escrow_funded_at'),
            'completed' => $query->where('status', QuestStatus::Completed),
            'featured' => $query->whereHas('activeFeaturedListing'),
            'hidden' => $query->whereIn('status', [QuestStatus::Closed, QuestStatus::Archived, QuestStatus::CancelledByAdmin]),
            'expired' => $query->whereNotNull('listing_expires_at')->where('listing_expires_at', '<=', now()),
            'disputed' => $query->where('status', QuestStatus::InDispute),
            default => null,
        };

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('reference_code', 'like', '%'.$search.'%')
                    ->orWhere('id', $search)
                    ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                    ->orWhereHas('questCategory', fn (Builder $category) => $category->where('name', 'like', '%'.$search.'%')->orWhereHas('parent', fn (Builder $parent) => $parent->where('name', 'like', '%'.$search.'%')));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('admin_status')) {
            $query->where('admin_status', $request->input('admin_status'));
        }
        if ($request->filled('category_id')) {
            $query->where('quest_category_id', $request->integer('category_id'));
        }
        if ($request->filled('state_id')) {
            $query->where('state_id', $request->integer('state_id'));
        }
        if ($request->filled('budget_min')) {
            $query->where('budget_amount_minor', '>=', (int) round(((float) $request->input('budget_min')) * 100));
        }
        if ($request->filled('budget_max')) {
            $query->where('budget_amount_minor', '<=', (int) round(((float) $request->input('budget_max')) * 100));
        }
        if ($request->filled('posted_from')) {
            $query->whereDate('created_at', '>=', $request->input('posted_from'));
        }
        if ($request->filled('posted_to')) {
            $query->whereDate('created_at', '<=', $request->input('posted_to'));
        }
        if ($request->filled('project_type')) {
            $query->where('project_type', $request->input('project_type'));
        }
        if ($request->filled('verification_tier')) {
            $query->whereHas('client', fn (Builder $client) => $client->where('verification_tier', $request->input('verification_tier')));
        }
        if ($request->filled('proposals_min')) {
            $query->where('offers_count', '>=', $request->integer('proposals_min'));
        }
        if ($request->filled('proposals_max')) {
            $query->where('offers_count', '<=', $request->integer('proposals_max'));
        }
        if ($request->boolean('has_media')) {
            $query->has('files');
        }
        if ($request->filled('escrow_funded')) {
            $request->boolean('escrow_funded') ? $query->whereNotNull('escrow_funded_at') : $query->whereNull('escrow_funded_at');
        }
        if ($request->filled('flag_type') && $this->questFlagsAvailable()) {
            $query->whereHas('activeAdminQuestFlags', fn (Builder $flag) => $flag->where('type', $request->input('flag_type')));
        } elseif ($request->filled('flag_type')) {
            $query->whereRaw('1 = 0');
        }
        if ($request->boolean('has_notices')) {
            $query->whereHas('adminQuestNotices');
        }
    }

    private function row(Quest $quest): array
    {
        $boost = $quest->activeFeaturedListing->sortByDesc('id')->first();
        $flags = $this->questFlagsAvailable() && $quest->relationLoaded('activeAdminQuestFlags')
            ? $quest->activeAdminQuestFlags->sortBy(fn (AdminQuestFlag $flag) => ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3][$flag->priority] ?? 4)->values()
            : collect();

        return [
            'id' => $quest->id,
            'reference_code' => $quest->reference_code,
            'route_key' => $quest->getRouteKey(),
            'title' => $quest->title,
            'description_excerpt' => str(strip_tags((string) $quest->description))->squish()->limit(150)->toString(),
            'client' => [
                'id' => $quest->client?->id,
                'name' => $quest->client?->name,
                'email' => $quest->client?->email,
                'avatar_url' => $quest->client?->avatar_url,
                'verification_tier' => $quest->client?->verification_tier,
            ],
            'category' => [
                'parent' => $quest->questCategory?->parent?->name,
                'name' => $quest->questCategory?->name,
                'color' => $quest->questCategory?->icon_color ?: $quest->questCategory?->parent?->icon_color,
            ],
            'state' => $quest->stateModel?->name,
            'budget' => $this->money((int) $quest->budget_amount_minor),
            'budget_minor' => (int) $quest->budget_amount_minor,
            'proposals_count' => (int) ($quest->offers_count ?? 0),
            'proposal_capacity' => $quest->max_offers,
            'status' => $quest->status?->value ?? (string) $quest->status,
            'status_label' => $this->statusLabel($quest->status?->value ?? (string) $quest->status),
            'status_tone' => $this->statusTone($quest->status?->value ?? (string) $quest->status),
            'admin_status' => app(AdminQuestModerationService::class)->statusPayload($quest->admin_status),
            'admin_status_reason' => $quest->admin_status_reason,
            'admin_status_changed_at' => $quest->admin_status_changed_at?->toIso8601String(),
            'admin_status_changed_by' => $quest->adminStatusChangedBy?->name,
            'risk_signals' => $this->riskSignals($quest),
            'has_user_notice' => $quest->relationLoaded('adminQuestNotices')
                ? $quest->adminQuestNotices->contains(fn (AdminQuestNotice $notice) => $notice->visible_to_users)
                : false,
            'featured' => $boost ? [
                'id' => $boost->id,
                'tier' => $boost->tier,
                'label' => str($boost->tier)->headline()->append(' Boost')->toString(),
                'expires_at' => $boost->expires_at?->toIso8601String(),
                'expiring_soon' => $boost->expires_at?->between(now(), now()->addDay()) ?? false,
            ] : null,
            'escrow' => [
                'status' => $quest->escrow_status,
                'funded' => $quest->escrow_funded_at !== null,
            ],
            'flags' => $flags->map(fn (AdminQuestFlag $flag) => $this->flagRow($flag))->all(),
            'files_count' => null,
            'created_at' => $quest->created_at?->toIso8601String(),
            'updated_at' => $quest->updated_at?->toIso8601String(),
        ];
    }

    private function overview(Quest $quest): array
    {
        $clientId = $quest->client_id;

        return [
            'quest' => [
                ...$this->row($quest),
                'description' => $quest->description,
                'quest_category_id' => $quest->quest_category_id,
                'state_id' => $quest->state_id,
                'city' => $quest->city,
                'visibility' => $quest->visibility?->value,
                'project_type' => $quest->project_type?->value,
                'start_timing' => $quest->start_timing?->value,
                'scheduled_start_date' => $quest->scheduled_start_date?->toDateString(),
                'estimated_completion_days' => $quest->estimated_completion_days,
                'due_at' => $quest->due_at?->toIso8601String(),
                'location' => collect([$quest->city, $quest->localGovernment?->name, $quest->stateModel?->name])->filter()->join(', '),
            ],
            'client_context' => [
                'id' => $quest->client?->id,
                'name' => $quest->client?->name,
                'email' => $quest->client?->email,
                'avatar_url' => $quest->client?->avatar_url,
                'verification_tier' => $quest->client?->verification_tier,
                'quests_posted' => $clientId ? Quest::query()->where('client_id', $clientId)->count() : 0,
                'amount_spent' => $this->money((int) Quest::query()->where('client_id', $clientId)->sum('paid_out_minor')),
            ],
            'timeline' => $this->timeline($quest),
        ];
    }

    private function proposalPayload(Quest $quest): array
    {
        $offers = QuestOffer::query()
            ->with('freelancer:id,name,email,avatar_url,verification_tier')
            ->where('quest_id', $quest->id)
            ->latest()
            ->get();

        $amounts = $offers->pluck('quoted_amount_minor')->filter(fn ($value) => $value !== null)->map(fn ($value) => (int) $value);

        return [
            'summary' => [
                'total' => $offers->count(),
                'average' => $amounts->isNotEmpty() ? $this->money((int) round($amounts->avg())) : '—',
                'lowest' => $amounts->isNotEmpty() ? $this->money((int) $amounts->min()) : '—',
                'highest' => $amounts->isNotEmpty() ? $this->money((int) $amounts->max()) : '—',
                'quest_budget' => $this->money((int) $quest->budget_amount_minor),
            ],
            'items' => $offers->map(fn (QuestOffer $offer) => [
                'id' => $offer->id,
                'status' => $offer->status,
                'pitch' => $offer->pitch,
                'scope_detail' => $offer->scope_detail,
                'quoted_amount' => $this->money((int) $offer->quoted_amount_minor),
                'planned_start_date' => $offer->planned_start_date?->toDateString(),
                'planned_finish_date' => $offer->planned_finish_date?->toDateString(),
                'estimated_duration_days' => $offer->estimated_duration_days,
                'freelancer' => [
                    'id' => $offer->freelancer?->id,
                    'name' => $offer->freelancer?->name,
                    'email' => $offer->freelancer?->email,
                    'avatar_url' => $offer->freelancer?->avatar_url,
                    'verification_tier' => $offer->freelancer?->verification_tier,
                ],
                'created_at' => $offer->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function escrowPayload(Quest $quest): array
    {
        return [
            'has_contract' => $quest->freelancer_id !== null || $quest->accepted_quest_offer_id !== null,
            'contract' => [
                'freelancer' => $quest->freelancer?->only(['id', 'name', 'email', 'avatar_url']),
                'accepted_offer_id' => $quest->accepted_quest_offer_id,
                'escrow_status' => $quest->escrow_status,
                'funded_at' => $quest->escrow_funded_at?->toIso8601String(),
                'delivery_acknowledged_at' => $quest->delivery_acknowledged_at?->toIso8601String(),
                'release_authorized_at' => $quest->release_authorized_at?->toIso8601String(),
                'release_hold_until' => $quest->release_hold_until?->toIso8601String(),
                'release_hold_reason' => $quest->release_hold_reason,
                'paid_out' => $this->money((int) $quest->paid_out_minor),
                'refunded' => $this->money((int) $quest->refunded_minor),
                'receipt_url' => route('admin.contracts.receipt', $quest),
            ],
            'ledger' => app(FinancialControlCentreService::class)->escrowLedger($quest),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseControlsPayload(Quest $quest): array
    {
        return [
            'requires_authorization' => \App\Support\EscrowReleasePolicy::requiresSuperAdminAuthorization($quest),
            'has_authorization' => \App\Support\EscrowReleasePolicy::hasSuperAdminAuthorization($quest),
            'release_held' => \App\Support\EscrowReleasePolicy::isReleaseHeld($quest),
            'high_value_threshold' => \App\Support\NgnMoney::format(\App\Support\EscrowReleasePolicy::highValueAuthorizationMinor()),
            'amount' => \App\Support\NgnMoney::format(\App\Support\EscrowReleasePolicy::escrowAmountMinor($quest)),
        ];
    }

    private function mediaPayload(Quest $quest): array
    {
        return [
            'items' => $quest->files->map(fn (QuestFile $file) => [
                'id' => $file->id,
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $this->fileSize((int) $file->size_bytes),
                'url' => $file->url(),
                'is_image' => $file->isImage(),
                'stage' => 'Quest brief attachment',
                'uploaded_by' => $quest->client?->name,
                'created_at' => $file->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function activityPayload(Quest $quest): array
    {
        return [
            'items' => AdminActivityLog::query()
                ->with('actor:id,name,email')
                ->where('subject_type', Quest::class)
                ->where('subject_id', $quest->id)
                ->latest()
                ->limit(80)
                ->get()
                ->map(fn (AdminActivityLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->actor?->name,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ];
    }

    private function communicationsPayload(Quest $quest): array
    {
        return [
            'client' => $quest->client?->only(['id', 'name', 'email']),
            'freelancers' => QuestOffer::query()
                ->with('freelancer:id,name,email')
                ->where('quest_id', $quest->id)
                ->get()
                ->pluck('freelancer')
                ->filter()
                ->unique('id')
                ->values(),
            'messages' => [],
        ];
    }

    private function timeline(Quest $quest): array
    {
        return collect([
            ['label' => 'Quest created', 'actor' => $quest->client?->name, 'at' => $quest->created_at?->toIso8601String()],
            $quest->escrow_funded_at ? ['label' => 'Escrow funded', 'actor' => 'System', 'at' => $quest->escrow_funded_at->toIso8601String()] : null,
            $quest->completed_at ? ['label' => 'Quest completed', 'actor' => 'System', 'at' => $quest->completed_at->toIso8601String()] : null,
        ])->filter()->values()->all();
    }

    private function filters(Request $request): array
    {
        return $request->only([
            'q', 'quick', 'status', 'admin_status', 'category_id', 'state_id', 'budget_min', 'budget_max',
            'posted_from', 'posted_to', 'project_type', 'verification_tier', 'proposals_min',
            'proposals_max', 'has_media', 'escrow_funded', 'flag_type', 'sort', 'per_page',
        ]);
    }

    private function options(): array
    {
        return [
            'status_options' => collect(QuestStatus::cases())->map(fn (QuestStatus $status) => [
                'value' => $status->value,
                'label' => $this->statusLabel($status->value),
            ])->values(),
            'admin_status_options' => app(AdminQuestModerationService::class)->statusOptions(),
            'quick_filters' => [
                ['value' => '', 'label' => 'All'],
                ['value' => 'open', 'label' => 'Open'],
                ...collect(AdminQuestStatus::cases())->map(fn (AdminQuestStatus $status) => [
                    'value' => 'admin:'.$status->value,
                    'label' => $status->label(),
                ])->all(),
            ],
            'flag_types' => ['suspicious_content', 'off_platform_solicitation', 'budget_anomaly', 'duplicate_quest', 'fraudulent_posting', 'policy_violation', 'client_complaint', 'needs_featured_review', 'requires_escrow_attention', 'other'],
            'flag_priorities' => ['low', 'medium', 'high', 'critical'],
            'flag_groups' => ['all_moderation_admins', 'all_finance_admins', 'all_super_admins'],
            'boost_tiers' => [
                'standard' => ['label' => 'Standard Boost', 'durations' => [3, 7], 'description' => 'Category-top visibility and featured badge.'],
                'premium' => ['label' => 'Premium Boost', 'durations' => [7, 14], 'description' => 'Category-top visibility, homepage carousel, and stronger discovery.'],
                'elite' => ['label' => 'Elite Boost', 'durations' => [14, 30], 'description' => 'Homepage carousel, weekly digest, and social post workflow.'],
            ],
            'grant_reasons' => ['client_retention', 'platform_promotion', 'compensation_for_issue', 'beta_tester_reward', 'other'],
        ];
    }

    private function editOptions(): array
    {
        return [
            'categories' => QuestCategory::query()
                ->where('status', 'active')
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id'])
                ->map(fn (QuestCategory $category) => ['value' => $category->id, 'label' => $category->parent_id ? '— '.$category->name : $category->name])
                ->values(),
            'states' => State::query()->orderBy('name')->get(['id', 'name'])->map(fn (State $state) => ['value' => $state->id, 'label' => $state->name])->values(),
            'visibility' => [
                ['value' => 'public', 'label' => 'Public'],
                ['value' => 'invite_only', 'label' => 'Invite only'],
                ['value' => 'private', 'label' => 'Private'],
            ],
            'project_types' => [
                ['value' => 'fixed_price', 'label' => 'Fixed price'],
                ['value' => 'hourly', 'label' => 'Hourly'],
            ],
            'start_timing' => [
                ['value' => 'urgent_48h', 'label' => 'Urgent, within 48 hours'],
                ['value' => 'this_week', 'label' => 'This week'],
                ['value' => 'next_two_weeks', 'label' => 'Next two weeks'],
                ['value' => 'flexible', 'label' => 'Flexible'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'window_shopping', 'label' => 'Window shopping'],
            ],
        ];
    }

    private function flagRow(AdminQuestFlag $flag): array
    {
        return [
            'id' => $flag->id,
            'type' => $flag->type,
            'priority' => $flag->priority,
            'description' => $flag->description,
            'due_at' => $flag->due_at?->toDateString(),
            'status' => $flag->status,
            'creator' => $flag->creator?->name,
            'assignee' => $flag->assignee?->name,
            'assigned_group' => $flag->assigned_group,
            'created_at' => $flag->created_at?->toIso8601String(),
        ];
    }

    private function noticeRow(AdminQuestNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'type' => $notice->type,
            'body' => $notice->body,
            'visible_to_users' => (bool) $notice->visible_to_users,
            'creator' => $notice->creator?->name,
            'created_at' => $notice->created_at?->toIso8601String(),
        ];
    }

    private function noteRow(AdminQuestNote $note): array
    {
        return [
            'id' => $note->id,
            'body' => $note->body,
            'is_pinned' => (bool) $note->is_pinned,
            'parent_id' => $note->parent_id,
            'admin' => [
                'name' => $note->admin?->name,
                'email' => $note->admin?->email,
                'avatar_url' => $note->admin?->avatar_url,
            ],
            'created_at' => $note->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{name: string, level: string, explanation: string, evidence: string}>
     */
    private function riskSignals(Quest $quest): array
    {
        $signals = [];
        $text = strtolower(strip_tags((string) $quest->description.' '.$quest->title));

        if (preg_match('/(\+?234|0)[789][01]\d{8}|whatsapp|telegram|bank transfer|direct payment|instagram|http(s)?:\/\//i', $text)) {
            $signals[] = [
                'name' => 'Off-platform contact/payment signal',
                'level' => 'high',
                'explanation' => 'The brief may contain phone, social, URL, or direct-payment language.',
                'evidence' => 'Matched contact or payment phrase in the Quest content.',
            ];
        }

        if ((int) $quest->budget_amount_minor > 5_000_000 && $quest->client?->created_at?->greaterThan(now()->subHours(48))) {
            $signals[] = [
                'name' => 'New account high-value Quest',
                'level' => 'medium',
                'explanation' => 'A new client account posted a comparatively high-value Quest.',
                'evidence' => $this->money((int) $quest->budget_amount_minor).' budget from a recently created account.',
            ];
        }

        if ((int) ($quest->offers_count ?? 0) === 0 && $quest->created_at?->lessThan(now()->subHours(72))) {
            $signals[] = [
                'name' => 'No proposals after 72 hours',
                'level' => 'low',
                'explanation' => 'The Quest may need review for scope, category fit, budget, or policy concerns.',
                'evidence' => '0 proposals since '.$quest->created_at?->timezone('Africa/Lagos')->toDayDateTimeString(),
            ];
        }

        return $signals;
    }

    private function questFlagsAvailable(): bool
    {
        if ($this->questFlagsTableExists !== null) {
            return $this->questFlagsTableExists;
        }

        try {
            $this->questFlagsTableExists = Schema::hasTable('admin_quest_flags');
        } catch (\Throwable) {
            $this->questFlagsTableExists = false;
        }

        return $this->questFlagsTableExists;
    }

    private function statusLabel(string $status): string
    {
        return str($status)->replace('_', ' ')->headline()->toString();
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'open' => 'primary',
            'assigned', 'in_progress' => 'blue',
            'completed' => 'green',
            'in_dispute' => 'red',
            'closed', 'archived' => 'gray',
            'cancelled_by_admin' => 'dark_red',
            'pending_review' => 'amber',
            default => 'slate',
        };
    }

    private function money(int|float|null $minor): string
    {
        return '₦'.number_format(((int) $minor) / 100, 2);
    }

    private function fileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
