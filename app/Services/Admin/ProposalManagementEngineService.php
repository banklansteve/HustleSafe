<?php

namespace App\Services\Admin;

use App\Enums\AdminProposalStatus;
use App\Models\AdminActivityLog;
use App\Models\AdminProposalFlag;
use App\Models\AdminProposalNote;
use App\Models\AdminProposalNotice;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestOffer;
use App\Models\User;
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

class ProposalManagementEngineService
{
    private ?bool $adminStatusColumnExists = null;
    private ?bool $proposalNoticesTableExists = null;
    private ?bool $proposalNotesTableExists = null;
    private ?bool $proposalFlagsTableExists = null;

    public function __construct(
        private readonly AdminActivityLogger $activity,
        private readonly VerificationEngineService $verificationEngine,
    ) {}

    public function dashboard(Request $request): array
    {
        return [
            'summary' => $this->summary(),
            'proposals' => $this->listing($request),
            'filters' => $this->filters($request),
            'options' => $this->options(),
        ];
    }

    public function summary(): array
    {
        return Cache::remember('admin.proposal-engine.summary', now()->addSeconds(20), fn () => $this->freshSummary());
    }

    private function freshSummary(): array
    {
        $adminCounts = $this->adminStatusAvailable()
            ? QuestOffer::query()
                ->selectRaw('COALESCE(admin_status, ?) as admin_status, count(*) as aggregate', [AdminProposalStatus::Clear->value])
                ->groupBy('admin_status')
                ->pluck('aggregate', 'admin_status')
            : collect([AdminProposalStatus::Clear->value => QuestOffer::query()->count()]);

        $createdToday = QuestOffer::query()->where('created_at', '>=', now()->subDay())->count();
        $autoFlagged = $this->flagsAvailable()
            ? AdminProposalFlag::query()->where('status', 'open')->whereIn('type', ['off_platform_contact', 'solicitation', 'lowball_bid', 'copy_paste', 'velocity_spam', 'high_value_low_tier'])->distinct('quest_offer_id')->count('quest_offer_id')
            : 0;
        $manualFlagged = $this->flagsAvailable()
            ? AdminProposalFlag::query()->where('status', 'open')->whereNotIn('type', ['off_platform_contact', 'solicitation', 'lowball_bid', 'copy_paste', 'velocity_spam', 'high_value_low_tier'])->distinct('quest_offer_id')->count('quest_offer_id')
            : 0;

        $submittedToday = max(1, $createdToday);
        $acceptedToday = QuestOffer::query()
            ->where('accepted_at', '>=', now()->startOfDay())
            ->orWhere(fn (Builder $query) => $query->where('status', 'accepted')->where('updated_at', '>=', now()->startOfDay()))
            ->count();

        return [
            ['key' => 'last_24h', 'label' => 'Proposals in last 24h', 'value' => $createdToday, 'filter' => ['quick' => 'last_24h']],
            ['key' => 'auto_flagged', 'label' => 'Auto-flagged proposals', 'value' => $autoFlagged, 'filter' => ['quick' => 'auto_flagged']],
            ['key' => 'manual_flagged', 'label' => 'Manually flagged proposals', 'value' => $manualFlagged, 'filter' => ['quick' => 'manual_flagged']],
            ['key' => 'under_review', 'label' => 'Under Review', 'value' => (int) ($adminCounts[AdminProposalStatus::UnderReview->value] ?? 0), 'filter' => ['admin_status' => AdminProposalStatus::UnderReview->value]],
            ['key' => 'referred', 'label' => 'Referred', 'value' => (int) ($adminCounts[AdminProposalStatus::Referred->value] ?? 0), 'filter' => ['admin_status' => AdminProposalStatus::Referred->value]],
            ['key' => 'action_required', 'label' => 'Action Required', 'value' => (int) ($adminCounts[AdminProposalStatus::ActionRequired->value] ?? 0), 'filter' => ['admin_status' => AdminProposalStatus::ActionRequired->value]],
            ['key' => 'suspended', 'label' => 'Suspended', 'value' => (int) ($adminCounts[AdminProposalStatus::Suspended->value] ?? 0), 'filter' => ['admin_status' => AdminProposalStatus::Suspended->value]],
            ['key' => 'off_platform', 'label' => 'Off-platform contact detected', 'value' => $this->countRisk('off_platform'), 'filter' => ['quick' => 'off_platform']],
            ['key' => 'high_value_low_tier', 'label' => 'High-value proposals from low-tier freelancers', 'value' => $this->countRisk('high_value_low_tier'), 'filter' => ['quick' => 'high_value_low_tier']],
            ['key' => 'prior_actions', 'label' => 'Freelancers with prior admin actions', 'value' => $this->countRisk('prior_actions'), 'filter' => ['quick' => 'prior_actions']],
            ['key' => 'conversion_today', 'label' => 'Today proposal-to-hire conversion rate', 'value' => round(($acceptedToday / $submittedToday) * 100).'%', 'filter' => ['quick' => 'accepted_today']],
        ];
    }

    public function listing(Request $request): LengthAwarePaginator
    {
        $relations = [
            'quest:id,reference_code,slug,title,budget_amount_minor,quest_category_id,status,admin_status,client_id,created_at',
            'quest.questCategory:id,name,parent_id,icon_color',
            'quest.questCategory.parent:id,name,icon_color',
            'quest.client:id,name,email,avatar_url',
            'freelancer:id,name,email,avatar_url,verification_tier,created_at',
            'freelancer.trustMetrics:user_id,freelancer_trust_score,profile_completion_percent,avg_rating_as_freelancer,ratings_count_as_freelancer',
        ];

        if ($this->adminStatusAvailable()) {
            $relations[] = 'adminStatusChangedBy:id,name,email';
        }
        if ($this->noticesAvailable()) {
            $relations[] = 'adminProposalNotices:id,quest_offer_id,type,visible_to_freelancer,visible_to_client';
        }
        if ($this->flagsAvailable()) {
            $relations[] = 'activeAdminProposalFlags:id,quest_offer_id,type,priority,description,visibility_impact,due_at,status,assigned_group,created_at';
        }

        $columns = [
                'id',
                'quest_id',
                'freelancer_id',
                'status',
                'pitch',
                'scope_detail',
                'warranty_terms',
                'quoted_amount_minor',
                'estimated_duration_days',
                'planned_start_date',
                'planned_finish_date',
                'accepted_at',
                'declined_at',
                'withdrawn_at',
                'shortlisted_at',
                'client_view_count',
                'last_client_view_at',
                'created_at',
                'updated_at',
        ];

        if ($this->adminStatusAvailable()) {
            array_push($columns, 'admin_status', 'admin_status_reason', 'admin_status_changed_by', 'admin_status_changed_at', 'admin_notice_severity');
        }

        $query = QuestOffer::query()
            ->select($columns)
            ->with($relations);

        $this->applyFilters($query, $request);

        $sort = (string) $request->input('sort', '-created_at');
        match ($sort) {
            'amount' => $query->orderBy('quoted_amount_minor'),
            '-amount' => $query->orderByDesc('quoted_amount_minor'),
            'trust' => $query->orderBy(
                User::query()->select('id')->whereColumn('users.id', 'quest_offers.freelancer_id')->limit(1)
            ),
            'created_at' => $query->orderBy('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return $query
            ->paginate(min(100, max(25, $request->integer('per_page', 25))))
            ->withQueryString()
            ->through(fn (QuestOffer $proposal) => $this->row($proposal));
    }

    public function detail(QuestOffer $proposal): array
    {
        $proposal->loadMissing([
            'quest:id,reference_code,slug,title,description,budget_amount_minor,quest_category_id,status,admin_status,client_id,max_offers,offers_count,created_at',
            'quest.questCategory.parent',
            'quest.client:id,name,email,avatar_url,verification_tier,created_at',
            'freelancer:id,name,slug,email,avatar_url,verification_tier,created_at,headline,bio',
            'freelancer.trustMetrics',
        ]);

        if ($this->adminStatusAvailable()) {
            $proposal->loadMissing('adminStatusChangedBy:id,name,email');
        }
        if ($this->noticesAvailable()) {
            $proposal->loadMissing('adminProposalNotices.creator:id,name,email');
        }
        if ($this->notesAvailable()) {
            $proposal->loadMissing('adminProposalNotes.admin:id,name,email,avatar_url');
        }
        if ($this->flagsAvailable()) {
            $proposal->loadMissing([
                'activeAdminProposalFlags.creator:id,name,email',
                'activeAdminProposalFlags.assignee:id,name,email',
            ]);
        }

        return [
            'overview' => $this->overview($proposal),
            'content' => $this->contentPayload($proposal),
            'risk' => $this->riskPayload($proposal),
            'freelancer' => $this->freelancerPayload($proposal),
            'quest' => $this->questPayload($proposal),
            'flags' => $this->flagsAvailable() && $proposal->relationLoaded('activeAdminProposalFlags')
                ? $proposal->activeAdminProposalFlags->map(fn (AdminProposalFlag $flag) => $this->flagRow($flag))->values()
                : collect(),
            'notices' => $this->noticesAvailable() && $proposal->relationLoaded('adminProposalNotices')
                ? $proposal->adminProposalNotices->sortByDesc('created_at')->map(fn (AdminProposalNotice $notice) => $this->noticeRow($notice))->values()
                : collect(),
            'notes' => $this->notesAvailable() && $proposal->relationLoaded('adminProposalNotes')
                ? $proposal->adminProposalNotes->sortByDesc('is_pinned')->sortByDesc('created_at')->map(fn (AdminProposalNote $note) => $this->noteRow($note))->values()
                : collect(),
            'activity' => $this->activityPayload($proposal),
            'communications' => [
                'freelancer' => $proposal->freelancer?->only(['id', 'name', 'slug', 'email']),
                'client' => $proposal->quest?->client?->only(['id', 'name', 'email']),
                'messages' => [],
            ],
            'edit_options' => [
                'can_edit_content' => request()->user()?->role?->slug === 'super_admin',
            ],
        ];
    }

    public function updateContent(QuestOffer $proposal, User $admin, array $data, Request $request): QuestOffer
    {
        $before = $proposal->only(['pitch', 'scope_detail', 'warranty_terms', 'quoted_amount_minor', 'estimated_duration_days']);
        $updates = collect($data)->only(array_keys($before))->all();

        $proposal->forceFill($updates)->save();

        $after = $proposal->refresh()->only(array_keys($before));
        $diff = collect($after)
            ->filter(fn ($value, $key) => ($before[$key] ?? null) !== $value)
            ->map(fn ($value, $key) => ['before' => $before[$key] ?? null, 'after' => $value])
            ->all();

        $this->activity->log($admin, 'admin.proposal.content_updated', QuestOffer::class, $proposal->id, [
            'reason' => $data['reason'],
            'diff' => $diff,
            'operational_status_unchanged' => $proposal->status,
            'notify_freelancer' => (bool) ($data['notify_freelancer'] ?? false),
            'notify_client' => (bool) ($data['notify_client'] ?? false),
        ], $request);

        return $proposal->refresh();
    }

    public function deleteProposal(QuestOffer $proposal, User $admin, array $data, Request $request): void
    {
        $proposalId = $proposal->id;
        $questId = $proposal->quest_id;
        $status = $proposal->status;

        $this->activity->log($admin, 'admin.proposal.deleted', QuestOffer::class, $proposalId, [
            'quest_id' => $questId,
            'freelancer_id' => $proposal->freelancer_id,
            'operational_status_at_delete' => $status,
            'reason' => $data['reason'] ?? null,
            'confirmation' => $data['confirmation'] ?? null,
        ], $request);

        $proposal->delete();
        Cache::forget('admin.proposal-engine.summary');
    }

    public function exportQuery(Request $request): Builder
    {
        $query = QuestOffer::query()->with(['quest:id,reference_code,title', 'freelancer:id,name,email']);
        $this->applyFilters($query, $request);

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $quick = (string) $request->input('quick', '');
        if (str_starts_with($quick, 'admin:')) {
            if ($this->adminStatusAvailable()) {
                $query->where('admin_status', str($quick)->after('admin:')->toString());
            } elseif (str($quick)->after('admin:')->toString() !== AdminProposalStatus::Clear->value) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        match ($quick) {
            'last_24h' => $query->where('quest_offers.created_at', '>=', now()->subDay()),
            'auto_flagged' => $this->flagsAvailable() ? $query->whereHas('activeAdminProposalFlags', fn (Builder $flag) => $flag->whereIn('type', ['off_platform_contact', 'solicitation', 'lowball_bid', 'copy_paste', 'velocity_spam', 'high_value_low_tier'])) : $query->whereRaw('1 = 0'),
            'manual_flagged' => $this->flagsAvailable() ? $query->whereHas('activeAdminProposalFlags', fn (Builder $flag) => $flag->whereNotIn('type', ['off_platform_contact', 'solicitation', 'lowball_bid', 'copy_paste', 'velocity_spam', 'high_value_low_tier'])) : $query->whereRaw('1 = 0'),
            'off_platform' => $query->where(fn (Builder $sub) => $sub->where('pitch', 'regexp', '(\\+?234|0)[789][01][0-9]{8}|whatsapp|telegram|instagram|http|www\\.')->orWhere('scope_detail', 'regexp', '(\\+?234|0)[789][01][0-9]{8}|whatsapp|telegram|instagram|http|www\\.')),
            'high_value_low_tier' => $this->applyHighValueLowTierFilter($query),
            'prior_actions' => $query->whereHas('freelancer', fn (Builder $user) => $user->whereIn('id', AdminActivityLog::query()->select('subject_id')->where('subject_type', User::class))),
            'accepted_today' => $query->where('status', 'accepted')->where('updated_at', '>=', now()->startOfDay()),
            default => null,
        };

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('pitch', 'like', '%'.$search.'%')
                    ->orWhere('scope_detail', 'like', '%'.$search.'%')
                    ->orWhere('id', $search)
                    ->orWhereHas('freelancer', fn (Builder $freelancer) => $freelancer->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                    ->orWhereHas('quest', fn (Builder $quest) => $quest->where('title', 'like', '%'.$search.'%')->orWhere('reference_code', 'like', '%'.$search.'%'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('admin_status')) {
            if ($this->adminStatusAvailable()) {
                $query->where('admin_status', $request->input('admin_status'));
            } elseif ($request->input('admin_status') !== AdminProposalStatus::Clear->value) {
                $query->whereRaw('1 = 0');
            }
        }
        if ($request->filled('category_id')) {
            $query->whereHas('quest', fn (Builder $quest) => $quest->where('quest_category_id', $request->integer('category_id')));
        }
        if ($request->filled('quest_id')) {
            $query->where('quest_id', $request->integer('quest_id'));
        }
        if ($request->filled('freelancer_id')) {
            $query->where('freelancer_id', $request->integer('freelancer_id'));
        }
        if ($request->filled('amount_min')) {
            $query->where('quoted_amount_minor', '>=', (int) round(((float) $request->input('amount_min')) * 100));
        }
        if ($request->filled('amount_max')) {
            $query->where('quoted_amount_minor', '<=', (int) round(((float) $request->input('amount_max')) * 100));
        }
        if ($request->filled('budget_min')) {
            $query->whereHas('quest', fn (Builder $quest) => $quest->where('budget_amount_minor', '>=', (int) round(((float) $request->input('budget_min')) * 100)));
        }
        if ($request->filled('budget_max')) {
            $query->whereHas('quest', fn (Builder $quest) => $quest->where('budget_amount_minor', '<=', (int) round(((float) $request->input('budget_max')) * 100)));
        }
        if ($request->filled('submitted_from')) {
            $query->whereDate('quest_offers.created_at', '>=', $request->input('submitted_from'));
        }
        if ($request->filled('submitted_to')) {
            $query->whereDate('quest_offers.created_at', '<=', $request->input('submitted_to'));
        }
        if ($request->filled('verification_tier')) {
            $query->whereHas('freelancer', fn (Builder $freelancer) => $freelancer->where('verification_tier', $request->input('verification_tier')));
        }
        if ($request->filled('trust_min')) {
            $query->whereHas('freelancer.trustMetrics', fn (Builder $metrics) => $metrics->where('freelancer_trust_score', '>=', $request->integer('trust_min')));
        }
        if ($request->filled('acting_admin_id')) {
            $this->adminStatusAvailable()
                ? $query->where('admin_status_changed_by', $request->integer('acting_admin_id'))
                : $query->whereRaw('1 = 0');
        }
        if ($request->filled('flag_type') && $this->flagsAvailable()) {
            $query->whereHas('activeAdminProposalFlags', fn (Builder $flag) => $flag->where('type', $request->input('flag_type')));
        } elseif ($request->filled('flag_type')) {
            $query->whereRaw('1 = 0');
        }
        if ($request->boolean('has_notice')) {
            $this->adminStatusAvailable()
                ? $query->whereNotNull('admin_notice_severity')
                : $query->whereRaw('1 = 0');
        }
    }

    private function row(QuestOffer $proposal): array
    {
        $flags = $this->flagsAvailable() && $proposal->relationLoaded('activeAdminProposalFlags')
            ? $proposal->activeAdminProposalFlags->sortBy(fn (AdminProposalFlag $flag) => ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3][$flag->priority] ?? 4)->values()
            : collect();
        $riskSignals = $this->riskSignals($proposal);
        $quest = $proposal->quest;
        $budget = (int) ($quest?->budget_amount_minor ?? 0);
        $amount = (int) ($proposal->quoted_amount_minor ?? 0);

        return [
            'id' => $proposal->id,
            'reference_code' => 'HSP-'.$proposal->id,
            'cover_letter_excerpt' => Str::of(strip_tags((string) $proposal->pitch))->squish()->limit(170)->toString(),
            'freelancer' => [
                'id' => $proposal->freelancer?->id,
                'name' => $proposal->freelancer?->name,
                'slug' => $proposal->freelancer?->slug,
                'email' => $proposal->freelancer?->email,
                'avatar_url' => $proposal->freelancer?->avatar_url,
                'verification_tier' => $proposal->freelancer?->verification_tier ?? 'Unverified',
                'trust_score' => (int) ($proposal->freelancer?->trust_score ?? 0),
            ],
            'quest' => [
                'id' => $quest?->id,
                'reference_code' => $quest?->reference_code,
                'route_key' => $quest?->getRouteKey(),
                'title' => $quest?->title,
                'budget' => $this->money($budget),
                'budget_minor' => $budget,
                'category' => $quest?->questCategory?->name,
                'client' => $quest?->client?->only(['id', 'name', 'email', 'avatar_url']),
            ],
            'proposed_amount' => $this->money($amount),
            'quoted_amount_minor' => $amount,
            'bid_variance' => $this->bidVariance($amount, $budget),
            'status' => (string) $proposal->status,
            'status_label' => $this->statusLabel((string) $proposal->status),
            'status_tone' => $this->statusTone((string) $proposal->status),
            'admin_status' => app(AdminProposalModerationService::class)->statusPayload($proposal->admin_status),
            'admin_status_reason' => $proposal->admin_status_reason,
            'admin_status_changed_at' => $proposal->admin_status_changed_at?->toIso8601String(),
            'admin_status_changed_by' => $proposal->adminStatusChangedBy?->name,
            'flags' => $flags->map(fn (AdminProposalFlag $flag) => $this->flagRow($flag))->all(),
            'flag_count' => $flags->count(),
            'has_user_notice' => $proposal->relationLoaded('adminProposalNotices')
                ? $proposal->adminProposalNotices->contains(fn (AdminProposalNotice $notice) => $notice->visible_to_freelancer || $notice->visible_to_client)
                : false,
            'risk_signals' => $riskSignals,
            'risk_score' => $this->riskScore($riskSignals),
            'created_at' => $proposal->created_at?->toIso8601String(),
            'updated_at' => $proposal->updated_at?->toIso8601String(),
        ];
    }

    private function overview(QuestOffer $proposal): array
    {
        return [
            'proposal' => [
                ...$this->row($proposal),
                'pitch' => $proposal->pitch,
                'scope_detail' => $proposal->scope_detail,
                'warranty_terms' => $proposal->warranty_terms,
                'estimated_duration_days' => $proposal->estimated_duration_days,
                'planned_start_date' => $proposal->planned_start_date?->toDateString(),
                'planned_finish_date' => $proposal->planned_finish_date?->toDateString(),
                'client_view_count' => (int) ($proposal->client_view_count ?? 0),
                'last_client_view_at' => $proposal->last_client_view_at?->toIso8601String(),
            ],
            'timeline' => collect([
                ['label' => 'Proposal submitted', 'actor' => $proposal->freelancer?->name, 'at' => $proposal->created_at?->toIso8601String()],
                $proposal->shortlisted_at ? ['label' => 'Shortlisted', 'actor' => 'Client', 'at' => $proposal->shortlisted_at->toIso8601String()] : null,
                $proposal->accepted_at ? ['label' => 'Accepted', 'actor' => 'Client', 'at' => $proposal->accepted_at->toIso8601String()] : null,
                $proposal->withdrawn_at ? ['label' => 'Withdrawn', 'actor' => $proposal->freelancer?->name, 'at' => $proposal->withdrawn_at->toIso8601String()] : null,
                $proposal->admin_status_changed_at ? ['label' => 'Admin status changed', 'actor' => $proposal->adminStatusChangedBy?->name, 'at' => $proposal->admin_status_changed_at->toIso8601String()] : null,
            ])->filter()->values(),
        ];
    }

    private function contentPayload(QuestOffer $proposal): array
    {
        return [
            'pitch' => $proposal->pitch,
            'scope_detail' => $proposal->scope_detail,
            'warranty_terms' => $proposal->warranty_terms,
            'proposed_amount' => $this->money((int) $proposal->quoted_amount_minor),
            'quoted_amount_minor' => (int) $proposal->quoted_amount_minor,
            'timeline' => collect([
                $proposal->planned_start_date ? 'Starts '.$proposal->planned_start_date->toFormattedDateString() : null,
                $proposal->planned_finish_date ? 'Finishes '.$proposal->planned_finish_date->toFormattedDateString() : null,
                $proposal->estimated_duration_days ? $proposal->estimated_duration_days.' days estimated' : null,
            ])->filter()->join(' · '),
            'attachments' => [],
            'submitted_at' => $proposal->created_at?->toIso8601String(),
        ];
    }

    private function riskPayload(QuestOffer $proposal): array
    {
        $signals = $this->riskSignals($proposal);

        return [
            'score' => $this->riskScore($signals),
            'signals' => $signals,
            'highlighted_phrases' => $this->highlightedPhrases($proposal),
            'similarity_score' => $this->similarityScore($proposal),
            'bid_analysis' => $this->bidAnalysis($proposal),
            'submission_velocity' => $this->submissionVelocity($proposal),
            'outlier_detection' => $this->outlierDetection($proposal),
        ];
    }

    private function freelancerPayload(QuestOffer $proposal): array
    {
        $freelancer = $proposal->freelancer;
        $recent = QuestOffer::query()
            ->with('quest:id,title,reference_code,budget_amount_minor')
            ->where('freelancer_id', $proposal->freelancer_id)
            ->latest()
            ->limit(15)
            ->get();

        return [
            'profile' => [
                'id' => $freelancer?->id,
                'name' => $freelancer?->name,
                'slug' => $freelancer?->slug,
                'email' => $freelancer?->email,
                'avatar_url' => $freelancer?->avatar_url,
                'verification_tier' => $freelancer?->verification_tier ?? 'Unverified',
                'trust_score' => (int) ($freelancer?->trust_score ?? 0),
                'profile_completion' => (int) ($freelancer?->profile_completion_percent ?? 0),
                'account_age' => $freelancer?->created_at?->diffForHumans(),
                'headline' => $freelancer?->headline,
            ],
            'stats' => [
                'acceptance_rate' => $this->percentage(
                    QuestOffer::query()->where('freelancer_id', $proposal->freelancer_id)->where('status', 'accepted')->count(),
                    max(1, QuestOffer::query()->where('freelancer_id', $proposal->freelancer_id)->count()),
                ),
                'completion_rate' => '—',
                'rating' => $freelancer?->avg_rating_as_freelancer ? number_format((float) $freelancer->avg_rating_as_freelancer, 1) : '—',
                'earnings' => '—',
                'dispute_history' => 'No linked dispute summary yet',
            ],
            'recent_proposals' => $recent->map(fn (QuestOffer $item) => [
                'id' => $item->id,
                'quest' => $item->quest?->title,
                'amount' => $this->money((int) $item->quoted_amount_minor),
                'status' => $this->statusLabel((string) $item->status),
                'risk_score' => $this->riskScore($this->riskSignals($item)),
                'created_at' => $item->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function questPayload(QuestOffer $proposal): array
    {
        $quest = $proposal->quest;
        $offers = QuestOffer::query()
            ->where('quest_id', $proposal->quest_id)
            ->orderBy('quoted_amount_minor')
            ->pluck('id')
            ->values();

        return [
            'summary' => [
                'id' => $quest?->id,
                'reference_code' => $quest?->reference_code,
                'route_key' => $quest?->getRouteKey(),
                'title' => $quest?->title,
                'description' => Str::of(strip_tags((string) $quest?->description))->squish()->limit(280)->toString(),
                'budget' => $this->money((int) $quest?->budget_amount_minor),
                'category' => $quest?->questCategory?->name,
                'status' => $quest?->status?->value ?? (string) $quest?->status,
                'admin_status' => $quest?->admin_status?->value ?? (string) $quest?->admin_status,
                'client' => $quest?->client?->only(['id', 'name', 'email', 'avatar_url']),
            ],
            'ranking' => [
                'by_amount' => max(1, $offers->search($proposal->id) + 1),
                'total' => $offers->count(),
            ],
        ];
    }

    private function activityPayload(QuestOffer $proposal): array
    {
        return [
            'items' => AdminActivityLog::query()
                ->with('actor:id,name,email')
                ->where('subject_type', QuestOffer::class)
                ->where('subject_id', $proposal->id)
                ->latest()
                ->limit(100)
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

    private function filters(Request $request): array
    {
        return $request->only([
            'q', 'quick', 'status', 'admin_status', 'category_id', 'quest_id', 'freelancer_id',
            'budget_min', 'budget_max', 'amount_min', 'amount_max', 'verification_tier', 'trust_min',
            'submitted_from', 'submitted_to', 'flag_type', 'acting_admin_id', 'has_notice', 'sort', 'per_page',
        ]);
    }

    private function options(): array
    {
        return [
            'status_options' => collect(['submitted', 'viewed', 'shortlisted', 'accepted', 'declined', 'rejected', 'withdrawn', 'expired'])->map(fn (string $status) => [
                'value' => $status,
                'label' => $this->statusLabel($status),
            ])->values(),
            'admin_status_options' => app(AdminProposalModerationService::class)->statusOptions(),
            'quick_filters' => [
                ['value' => '', 'label' => 'All'],
                ['value' => 'last_24h', 'label' => 'Last 24h'],
                ...collect(AdminProposalStatus::cases())->map(fn (AdminProposalStatus $status) => [
                    'value' => 'admin:'.$status->value,
                    'label' => $status->label(),
                ])->all(),
            ],
            'categories' => QuestCategory::query()
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id'])
                ->map(fn (QuestCategory $category) => ['value' => $category->id, 'label' => $category->parent_id ? '— '.$category->name : $category->name])
                ->values(),
            'flag_types' => ['off_platform_contact', 'solicitation', 'lowball_bid', 'copy_paste', 'velocity_spam', 'coordinated_bidding', 'high_value_low_tier', 'prior_admin_actions', 'policy_violation', 'other'],
            'flag_priorities' => ['low', 'medium', 'high', 'critical'],
            'flag_groups' => ['all_moderation_admins', 'all_finance_admins', 'all_super_admins'],
        ];
    }

    private function riskSignals(QuestOffer $proposal): array
    {
        $text = strtolower(strip_tags((string) $proposal->pitch.' '.$proposal->scope_detail.' '.$proposal->warranty_terms));
        $signals = [];
        $budget = (int) ($proposal->quest?->budget_amount_minor ?? 0);
        $amount = (int) ($proposal->quoted_amount_minor ?? 0);

        if (preg_match('/(\+?234|0)[789][01]\d{8}|whatsapp|telegram|instagram|http(s)?:\/\/|www\./i', $text)) {
            $signals[] = ['key' => 'off_platform_contact', 'name' => 'Off-platform contact detected', 'severity' => 'high', 'explanation' => 'The proposal appears to include phone, social, or URL contact details.', 'evidence' => 'Matched contact or external URL pattern in proposal text.'];
        }
        if (preg_match('/pay me direct|outside (the )?platform|bank transfer|avoid fees|cash/i', $text)) {
            $signals[] = ['key' => 'solicitation', 'name' => 'Off-platform solicitation', 'severity' => 'critical', 'explanation' => 'The wording may invite the client to transact away from HustleSafe.', 'evidence' => 'Matched direct-payment or fee-avoidance phrase.'];
        }
        if ($budget > 0 && $amount > 0 && $amount <= (int) round($budget * 0.4)) {
            $signals[] = ['key' => 'lowball_bid', 'name' => 'Lowball bid', 'severity' => 'medium', 'explanation' => 'The proposed amount is more than 60% below the Quest budget.', 'evidence' => $this->money($amount).' against '.$this->money($budget).' budget.'];
        }
        if (QuestOffer::query()->where('freelancer_id', $proposal->freelancer_id)->where('created_at', '>=', now()->subDay())->count() > 10) {
            $signals[] = ['key' => 'velocity_spam', 'name' => 'Velocity spam', 'severity' => 'high', 'explanation' => 'The freelancer has submitted more than 10 proposals in 24 hours.', 'evidence' => 'Submission velocity threshold exceeded.'];
        }
        if ($this->similarityScore($proposal) > 75) {
            $signals[] = ['key' => 'copy_paste', 'name' => 'Copy-paste similarity', 'severity' => 'medium', 'explanation' => 'This proposal is highly similar to the freelancer’s recent proposals.', 'evidence' => $this->similarityScore($proposal).'% similarity score.'];
        }
        $freelancer = $proposal->freelancer;
        if ($freelancer && $budget > 0 && $this->verificationEngine->exceedsFreelancerProposalLimit($freelancer, $budget)) {
            $context = $this->verificationEngine->freelancerProposalLimitAuditContext($freelancer, $budget);
            $signals[] = [
                'key' => 'high_value_low_tier',
                'name' => 'High-value Quest from low-tier freelancer',
                'severity' => 'high',
                'explanation' => 'The quest budget exceeds this freelancer\'s verification posting limit.',
                'evidence' => sprintf(
                    '%s quest budget exceeds %s limit of %s.',
                    $this->money($budget),
                    $context['limit_level_label'] ?? 'verification',
                    $this->money((int) ($context['limit_minor'] ?? 0)),
                ),
            ];
        }
        if (AdminActivityLog::query()->where('subject_type', User::class)->where('subject_id', $proposal->freelancer_id)->exists()) {
            $signals[] = ['key' => 'prior_admin_actions', 'name' => 'Prior admin actions', 'severity' => 'medium', 'explanation' => 'This freelancer has previous admin activity attached to their account.', 'evidence' => 'Admin activity log contains user-level entries.'];
        }

        return $signals;
    }

    private function highlightedPhrases(QuestOffer $proposal): array
    {
        $text = (string) $proposal->pitch.' '.(string) $proposal->scope_detail;
        preg_match_all('/(\+?234|0)[789][01]\d{8}|whatsapp|telegram|instagram|http(s)?:\/\/\S+|www\.\S+|bank transfer|outside the platform|pay me direct/i', $text, $matches);

        return collect($matches[0] ?? [])->unique()->values()->all();
    }

    private function similarityScore(QuestOffer $proposal): int
    {
        $current = Str::of(strip_tags((string) $proposal->pitch))->lower()->squish()->toString();
        if ($current === '') {
            return 0;
        }

        $recent = QuestOffer::query()
            ->where('freelancer_id', $proposal->freelancer_id)
            ->where('id', '<>', $proposal->id)
            ->latest()
            ->limit(5)
            ->pluck('pitch');

        return (int) $recent->map(function ($pitch) use ($current): int {
            similar_text($current, Str::of(strip_tags((string) $pitch))->lower()->squish()->toString(), $percent);

            return (int) round($percent);
        })->max() ?: 0;
    }

    private function bidAnalysis(QuestOffer $proposal): array
    {
        $questAmounts = QuestOffer::query()
            ->where('quest_id', $proposal->quest_id)
            ->whereNotNull('quoted_amount_minor')
            ->pluck('quoted_amount_minor')
            ->map(fn ($value) => (int) $value);

        return [
            'budget' => $this->money((int) ($proposal->quest?->budget_amount_minor ?? 0)),
            'proposal' => $this->money((int) $proposal->quoted_amount_minor),
            'average_competing_bid' => $questAmounts->isNotEmpty() ? $this->money((int) round($questAmounts->avg())) : '—',
            'variance' => $this->bidVariance((int) $proposal->quoted_amount_minor, (int) ($proposal->quest?->budget_amount_minor ?? 0)),
        ];
    }

    private function submissionVelocity(QuestOffer $proposal): array
    {
        $count = QuestOffer::query()->where('freelancer_id', $proposal->freelancer_id)->where('created_at', '>=', now()->subDay())->count();

        return [
            'last_24h' => $count,
            'label' => $count > 10 ? 'High velocity' : 'Normal velocity',
        ];
    }

    private function outlierDetection(QuestOffer $proposal): array
    {
        $history = QuestOffer::query()
            ->where('freelancer_id', $proposal->freelancer_id)
            ->where('id', '<>', $proposal->id)
            ->whereNotNull('quoted_amount_minor')
            ->pluck('quoted_amount_minor')
            ->map(fn ($value) => (int) $value);

        if ($history->isEmpty()) {
            return ['label' => 'No prior bid history', 'historical_average' => '—'];
        }

        $average = (int) round($history->avg());
        $amount = (int) $proposal->quoted_amount_minor;

        return [
            'label' => $amount > ($average * 2) || $amount < ($average * 0.5) ? 'Outlier vs history' : 'Within historical range',
            'historical_average' => $this->money($average),
        ];
    }

    private function riskScore(array $signals): int
    {
        $weights = ['low' => 8, 'medium' => 16, 'high' => 28, 'critical' => 40];

        return min(100, collect($signals)->sum(fn (array $signal) => $weights[$signal['severity'] ?? 'low'] ?? 8));
    }

    private function countRisk(string $risk): int
    {
        return QuestOffer::query()
            ->with(['quest:id,budget_amount_minor', 'freelancer:id,verification_tier'])
            ->latest()
            ->limit(500)
            ->get()
            ->filter(fn (QuestOffer $proposal) => collect($this->riskSignals($proposal))->contains('key', $risk))
            ->count();
    }

    private function bidVariance(int $amount, int $budget): array
    {
        if ($budget <= 0 || $amount <= 0) {
            return ['label' => 'No budget benchmark', 'tone' => 'gray', 'percent' => 0];
        }

        $percent = (int) round((($amount - $budget) / $budget) * 100);

        return [
            'label' => $percent === 0 ? 'On budget' : ($percent > 0 ? '+'.$percent.'% above' : abs($percent).'% below'),
            'tone' => $percent < -60 ? 'red' : ($percent < -25 ? 'amber' : ($percent > 25 ? 'blue' : 'green')),
            'percent' => $percent,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Submitted',
            'viewed' => 'Viewed',
            'shortlisted' => 'Shortlisted',
            'accepted' => 'Accepted',
            'declined', 'rejected' => 'Rejected',
            'withdrawn' => 'Withdrawn',
            'expired' => 'Expired',
            default => Str::of($status)->replace('_', ' ')->headline()->toString(),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'accepted' => 'green',
            'shortlisted', 'viewed' => 'blue',
            'declined', 'rejected', 'withdrawn', 'expired' => 'red',
            default => 'gray',
        };
    }

    private function flagRow(AdminProposalFlag $flag): array
    {
        return [
            'id' => $flag->id,
            'type' => $flag->type,
            'priority' => $flag->priority,
            'description' => $flag->description,
            'visibility_impact' => $flag->visibility_impact,
            'due_at' => $flag->due_at?->toDateString(),
            'status' => $flag->status,
            'creator' => $flag->creator?->name,
            'assignee' => $flag->assignee?->name,
            'assigned_group' => $flag->assigned_group,
            'created_at' => $flag->created_at?->toIso8601String(),
        ];
    }

    private function noticeRow(AdminProposalNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'type' => $notice->type,
            'body' => $notice->body,
            'visible_to_freelancer' => (bool) $notice->visible_to_freelancer,
            'visible_to_client' => (bool) $notice->visible_to_client,
            'creator' => $notice->creator?->name,
            'created_at' => $notice->created_at?->toIso8601String(),
        ];
    }

    private function noteRow(AdminProposalNote $note): array
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

    private function flagsAvailable(): bool
    {
        return $this->proposalFlagsTableExists ??= Schema::hasTable('admin_proposal_flags');
    }

    private function noticesAvailable(): bool
    {
        return $this->proposalNoticesTableExists ??= Schema::hasTable('admin_proposal_notices');
    }

    private function notesAvailable(): bool
    {
        return $this->proposalNotesTableExists ??= Schema::hasTable('admin_proposal_notes');
    }

    private function adminStatusAvailable(): bool
    {
        return $this->adminStatusColumnExists ??= Schema::hasColumn('quest_offers', 'admin_status');
    }

    private function percentage(int $numerator, int $denominator): string
    {
        return round(($numerator / max(1, $denominator)) * 100).'%';
    }

    private function applyHighValueLowTierFilter(Builder $query): void
    {
        $limitMap = $this->verificationEngine->limits()['freelancer_proposal_minor'] ?? [];
        if ($limitMap === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $levelExpr = 'coalesce(users.current_verification_level, users.verification_tier, 0)';
        $caseParts = [];
        foreach ($limitMap as $level => $limit) {
            $caseParts[] = 'when '.$levelExpr.' = '.(int) $level.' then '.(int) $limit;
        }

        $tierLimitCase = 'case '.implode(' ', $caseParts).' else 0 end';
        $effectiveLimitCase = 'case when users.custom_freelancer_proposal_limit_minor is not null then users.custom_freelancer_proposal_limit_minor else ('.$tierLimitCase.') end';

        $query->whereHas('quest', fn (Builder $quest) => $quest->where('budget_amount_minor', '>', 0))
            ->whereHas('freelancer', function (Builder $freelancer) use ($effectiveLimitCase): void {
                $freelancer->whereRaw(
                    '(select q.budget_amount_minor from quests q where q.id = quest_offers.quest_id limit 1) > ('.$effectiveLimitCase.')'
                );
            });
    }

    private function money(int $minor): string
    {
        return '₦'.number_format(max(0, $minor) / 100, 0);
    }
}
