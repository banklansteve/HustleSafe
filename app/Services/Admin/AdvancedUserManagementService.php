<?php

namespace App\Services\Admin;

use App\Enums\QuestDisputeStatus;
use App\Models\ActivityLog;
use App\Models\AdminActivityFeedEvent;
use App\Models\AdminUserBadge;
use App\Models\AdminUserNote;
use App\Models\AdminUserSanction;
use App\Models\AdminUserSegment;
use App\Models\AdminUserTag;
use App\Models\ContentReport;
use App\Models\LoginEvent;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\Review;
use App\Models\Role;
use App\Models\State;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\Contracts\DeliveryReliabilityScoreService;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdvancedUserManagementService
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginated(Request $request): LengthAwarePaginator
    {
        $perPage = min(250, max(10, (int) $request->integer('per_page', 25)));

        $query = User::query()
            ->with(['role:id,name,slug', 'stateModel:id,name', 'localGovernmentModel:id,name', 'questCategoryPreferences:id,name', 'adminTags:id,name,color', 'adminBadges:id,name,slug'])
            ->withCount([
                'adminNotes',
                'questOffers as proposals_count',
                'questsAsClient as client_quests_count',
                'questsAsFreelancer as freelancer_contracts_count',
                'userVerifications as pending_verifications_count' => fn (Builder $query) => $query->where('status', 'pending'),
            ]);

        $this->applyFilters($query, $request);
        $this->applySorting($query, (array) $request->input('sort', []));

        return $query->paginate($perPage)->withQueryString()->through(fn (User $user) => $this->row($user));
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(?User $admin = null): array
    {
        return [
            'roles' => Role::query()->whereIn('slug', ['client', 'freelancer', 'admin', 'super_admin'])->orderBy('name')->get(['id', 'name', 'slug']),
            'states' => State::query()->orderBy('name')->get(['id', 'name']),
            'categories' => QuestCategory::query()->whereNotNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'segments' => AdminUserSegment::query()
                ->where(function (Builder $query) use ($admin): void {
                    $query->whereNull('admin_user_id');
                    if ($admin !== null) {
                        $query->orWhere('admin_user_id', $admin->id);
                    }
                })
                ->latest()
                ->get(['id', 'name', 'filters']),
            'tags' => AdminUserTag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'badges' => AdminUserBadge::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'sanctionReasons' => [
                ['value' => 'fraud_risk', 'label' => 'Fraud risk'],
                ['value' => 'abuse_or_harassment', 'label' => 'Abuse or harassment'],
                ['value' => 'payment_risk', 'label' => 'Payment risk'],
                ['value' => 'identity_mismatch', 'label' => 'Identity mismatch'],
                ['value' => 'policy_violation', 'label' => 'Policy violation'],
                ['value' => 'dispute_pattern', 'label' => 'Dispute pattern'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(User $user, string $tab = 'overview'): array
    {
        $user->loadMissing([
            'role:id,name,slug',
            'stateModel:id,name',
            'localGovernmentModel:id,name',
            'questCategoryPreferences:id,name',
            'adminTags:id,name,color',
            'adminBadges:id,name,slug',
            'userVerifications.reviewer:id,name,email',
            'sanctions.admin:id,name,email',
        ]);

        return [
            'overview' => $this->overview($user),
            'tab' => $tab,
            'tabData' => match ($tab) {
                'activity' => $this->activity($user),
                'financials' => $this->financials($user),
                'contracts' => $this->contracts($user),
                'disputes' => $this->disputes($user),
                'reviews' => $this->reviews($user),
                'notes' => $this->notes($user),
                default => [],
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function row(User $user): array
    {
        $score = $this->trustScoreFor($user);
        $activityMinor = $this->activityMinor($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'nin' => $user->nin,
            'avatar_url' => $user->avatar_url,
            'company_name' => $user->company_name,
            'role' => $user->role?->slug ?? $user->account_type,
            'role_label' => Str::headline((string) ($user->role?->slug ?? $user->account_type ?? 'member')),
            'city' => $user->city,
            'state' => $user->stateModel?->name,
            'categories' => $user->questCategoryPreferences->pluck('name')->values(),
            'trust_score' => $score,
            'trust_band' => $score >= 75 ? 'green' : ($score >= 45 ? 'amber' : 'red'),
            'activity_label' => $this->money($activityMinor),
            'activity_minor' => $activityMinor,
            'account_status' => $this->status($user),
            'open_disputes_count' => $this->openDisputeCount($user),
            'is_verified' => $user->email_verified_at !== null && $user->userVerifications()->where('status', 'approved')->exists(),
            'is_flagged' => $this->isFlagged($user),
            'tags' => $user->adminTags->map(fn (AdminUserTag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])->values(),
            'badges' => $user->adminBadges->map(fn (AdminUserBadge $badge) => ['id' => $badge->id, 'name' => $badge->name, 'slug' => $badge->slug])->values(),
            'joined_at' => $user->created_at?->toIso8601String(),
            'last_active_at' => $user->last_active_at?->toIso8601String(),
            'notes_count' => (int) ($user->admin_notes_count ?? 0),
            'pending_verifications_count' => (int) ($user->pending_verifications_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(User $user): array
    {
        $lastLogin = LoginEvent::query()->where('user_id', $user->id)->latest('logged_in_at')->first();
        $verificationEngine = app(VerificationEngineService::class);

        return [
            'user' => $this->row($user),
            'profile' => [
                'username' => $user->username,
                'uid' => $user->uid,
                'bio' => $user->bio,
                'headline' => $user->headline,
                'profession' => $user->profession,
                'company_name' => $user->company_name,
                'address_line' => $user->address_line,
                'verification_tier' => $user->verification_tier,
                'last_login_at' => $lastLogin?->logged_in_at?->toIso8601String(),
                'last_login_device' => $lastLogin?->user_agent,
                'last_login_ip' => $lastLogin?->ip_address,
            ],
            'verification' => $user->userVerifications->map(fn (UserVerification $verification) => [
                'id' => $verification->id,
                'category' => $this->enumValue($verification->category),
                'status' => $this->enumValue($verification->status),
                'submitted_at' => $verification->submitted_at?->toIso8601String(),
                'reviewed_at' => $verification->reviewed_at?->toIso8601String(),
                'reviewer' => $verification->reviewer?->name,
                'rejection_reason' => $verification->rejection_reason,
                'metadata' => $verification->metadata ?? [],
            ])->values(),
            'verification_engine' => [
                'earned_level' => $verificationEngine->earnedLevel($user),
                'effective_level' => $verificationEngine->effectiveLevel($user),
                'earned_label' => $verificationEngine->levelLabel($verificationEngine->earnedLevel($user), $user),
                'effective_label' => $verificationEngine->levelLabel($verificationEngine->effectiveLevel($user), $user),
                'client_posting_limit_minor' => $verificationEngine->clientPostingLimitMinor($user),
                'freelancer_proposal_limit_minor' => $verificationEngine->freelancerProposalLimitMinor($user),
                'client_posting_limit_formatted' => $verificationEngine->formatMoneyMinor($verificationEngine->clientPostingLimitMinor($user)),
                'freelancer_proposal_limit_formatted' => $verificationEngine->formatMoneyMinor($verificationEngine->freelancerProposalLimitMinor($user)),
                'tier_catalog' => $verificationEngine->tierCatalogForRole($verificationEngine->isFreelancer($user)),
                'override' => [
                    'level' => $user->verification_level_override,
                    'reason' => $user->verification_level_override_reason,
                    'at' => $user->verification_level_overridden_at?->toIso8601String(),
                ],
                'restriction' => [
                    'active' => $user->verification_restricted_at !== null,
                    'reason' => $user->verification_restriction_reason,
                    'at' => $user->verification_restricted_at?->toIso8601String(),
                ],
                'anomaly_flags' => $user->verificationAnomalyFlags()
                    ->latest()
                    ->limit(20)
                    ->get()
                    ->map(fn ($flag) => [
                        'id' => $flag->id,
                        'type' => $flag->type,
                        'status' => $flag->status,
                        'severity' => $flag->severity,
                        'created_at' => $flag->created_at?->toIso8601String(),
                    ])
                    ->values(),
            ],
            'delivery_reliability' => ($user->role?->slug ?? '') === 'freelancer'
                ? app(DeliveryReliabilityScoreService::class)->snapshot($user)
                : null,
            'trust' => $this->trustBreakdown($user),
            'sanctions' => $user->sanctions->map(fn (AdminUserSanction $sanction) => [
                'id' => $sanction->id,
                'type' => $sanction->type,
                'reason_code' => $sanction->reason_code,
                'notes' => $sanction->notes,
                'starts_at' => $sanction->starts_at?->toIso8601String(),
                'ends_at' => $sanction->ends_at?->toIso8601String(),
                'reversed_at' => $sanction->reversed_at?->toIso8601String(),
                'admin' => $sanction->admin?->name,
            ])->values(),
        ];
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('company_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('nin', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('role')) {
            $role = (string) $request->input('role');
            $query->whereHas('role', fn (Builder $roleQuery) => $roleQuery->where('slug', $role));
        }

        if ($request->filled('status')) {
            match ((string) $request->input('status')) {
                'active' => $query->whereNull('suspended_at')->whereNull('under_review_at')->whereNull('banned_at')->whereNull('deactivated_at'),
                'suspended' => $query->whereNotNull('suspended_at'),
                'under_review' => $query->whereNotNull('under_review_at'),
                'banned' => $query->whereNotNull('banned_at'),
                'closed' => $query->whereNotNull('deactivated_at'),
                default => null,
            };
        }

        if ($request->filled('state_id')) {
            $query->where('state_id', $request->integer('state_id'));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('questCategoryPreferences', fn (Builder $categoryQuery) => $categoryQuery->whereKey($request->integer('category_id')));
        }

        if ($request->filled('verified')) {
            $request->boolean('verified')
                ? $query->whereNotNull('email_verified_at')
                : $query->whereNull('email_verified_at');
        }

        if ($request->boolean('flagged')) {
            $query->where(function (Builder $flagged): void {
                $flagged->whereHas('adminTags')
                    ->orWhereIn('id', ContentReport::query()
                        ->where('reportable_type', User::class)
                        ->where('status', 'open')
                        ->select('reportable_id'));
            });
        }

        if ($request->boolean('open_disputes')) {
            $openStatuses = [QuestDisputeStatus::Open->value, QuestDisputeStatus::SelfResolving->value, QuestDisputeStatus::Escalated->value, QuestDisputeStatus::AwaitingRuling->value];
            $query->where(function (Builder $disputeQuery) use ($openStatuses): void {
                $disputeQuery
                    ->whereHas('questsAsClient.disputes', fn (Builder $q) => $q->whereIn('status', $openStatuses))
                    ->orWhereHas('questsAsFreelancer.disputes', fn (Builder $q) => $q->whereIn('status', $openStatuses));
            });
        }

        if ($request->filled('joined_from')) {
            $query->whereDate('created_at', '>=', $request->input('joined_from'));
        }
        if ($request->filled('joined_to')) {
            $query->whereDate('created_at', '<=', $request->input('joined_to'));
        }

        if ($request->filled('trust_min')) {
            $query->whereHas('trustMetrics', function (Builder $trust) use ($request): void {
                $trust->where('freelancer_trust_score', '>=', $request->integer('trust_min'))
                    ->orWhere('client_trust_score', '>=', $request->integer('trust_min'));
            });
        }
        if ($request->filled('trust_max')) {
            $query->whereHas('trustMetrics', function (Builder $trust) use ($request): void {
                $trust->where('freelancer_trust_score', '<=', $request->integer('trust_max'))
                    ->orWhere('client_trust_score', '<=', $request->integer('trust_max'));
            });
        }
    }

    private function applySorting(Builder $query, array $sort): void
    {
        $allowed = ['name', 'email', 'created_at', 'last_active_at', 'city'];
        foreach ($sort as $item) {
            $column = (string) ($item['column'] ?? '');
            if (! in_array($column, $allowed, true)) {
                continue;
            }
            $query->orderBy($column, (($item['direction'] ?? 'asc') === 'desc') ? 'desc' : 'asc');
        }

        if (empty($sort)) {
            $query->orderByDesc('id');
        }
    }

    private function trustScoreFor(User $user): int
    {
        return $user->role?->slug === 'client' ? (int) $user->client_trust_score : (int) $user->trust_score;
    }

    private function activityMinor(User $user): int
    {
        if ($user->role?->slug === 'client') {
            return (int) Quest::query()->where('client_id', $user->id)->sum('budget_amount_minor');
        }

        return (int) Quest::query()->where('freelancer_id', $user->id)->sum('paid_out_minor');
    }

    private function status(User $user): string
    {
        if ($user->banned_at !== null) {
            return 'banned';
        }
        if ($user->suspended_at !== null) {
            return 'suspended';
        }
        if ($user->under_review_at !== null) {
            return 'under_review';
        }
        if ($user->deactivated_at !== null) {
            return 'closed';
        }

        return 'active';
    }

    private function openDisputeCount(User $user): int
    {
        $openStatuses = [QuestDisputeStatus::Open->value, QuestDisputeStatus::SelfResolving->value, QuestDisputeStatus::Escalated->value, QuestDisputeStatus::AwaitingRuling->value];

        return QuestDispute::query()
            ->whereIn('status', $openStatuses)
            ->whereHas('quest', fn (Builder $q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->count();
    }

    private function isFlagged(User $user): bool
    {
        return $user->adminTags->isNotEmpty() || ContentReport::query()
            ->where('reportable_type', User::class)
            ->where('reportable_id', $user->id)
            ->where('status', 'open')
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trustBreakdown(User $user): array
    {
        $completed = Quest::query()->where(function (Builder $q) use ($user): void {
            $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
        })->whereNotNull('completed_at')->count();
        $started = Quest::query()->where(function (Builder $q) use ($user): void {
            $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
        })->whereNotNull('accepted_quest_offer_id')->count();
        $lostDisputes = QuestDispute::query()
            ->whereHas('quest', fn (Builder $q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->where('ruling_favoured_user_id', '!=', $user->id)
            ->whereNotNull('resolved_at')
            ->count();

        return [
            ['label' => 'Profile completeness', 'score' => (int) $user->profile_completion_percent, 'weight' => 15],
            ['label' => 'Identity verification', 'score' => $user->userVerifications->where('status', 'approved')->isNotEmpty() ? 100 : ($user->email_verified_at ? 45 : 10), 'weight' => 20],
            ['label' => 'Payment history', 'score' => 80, 'weight' => 10],
            ['label' => 'Quest completion rate', 'score' => $started > 0 ? (int) round(($completed / max(1, $started)) * 100) : 50, 'weight' => 15],
            ['label' => 'Average rating received', 'score' => (int) round(((float) ($user->avg_rating_as_freelancer ?? $user->avg_rating_as_client ?? 0)) * 20), 'weight' => 15],
            ['label' => 'Dispute history', 'score' => max(0, 100 - ($lostDisputes * 20)), 'weight' => 15],
            ['label' => 'Account age', 'score' => min(100, max(10, (int) $user->created_at?->diffInDays(now()))), 'weight' => 5],
            ['label' => 'Response rate', 'score' => $user->response_time_hours ? max(0, 100 - ((int) $user->response_time_hours * 5)) : 50, 'weight' => 5],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activity(User $user): array
    {
        $userClass = User::class;
        $feed = class_exists(AdminActivityFeedEvent::class)
            ? AdminActivityFeedEvent::query()
                ->where(function (Builder $q) use ($user, $userClass): void {
                    $q->where(function (Builder $sub) use ($user, $userClass): void {
                        $sub->where('subject_type', $userClass)
                            ->where('subject_id', $user->id);
                    })
                        ->orWhere('actor_user_id', $user->id)
                        ->orWhereJsonContains('entities', [['type' => 'user', 'id' => $user->id]]);
                })
                ->latest('occurred_at')
                ->limit(60)
                ->get()
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'type' => $event->event_key,
                    'category' => $event->category,
                    'title' => $event->title,
                    'summary' => $event->summary,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ])
            : collect();

        $logs = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('activity_logs')
            && \Illuminate\Support\Facades\Schema::hasColumn('activity_logs', 'subject_user_id')) {
            $logs = ActivityLog::query()
                ->where('subject_user_id', $user->id)
                ->latest('created_at')
                ->limit(40)
                ->get()
                ->map(fn (ActivityLog $log) => [
                    'id' => 'log-'.$log->id,
                    'type' => $log->type,
                    'category' => 'admin',
                    'title' => $log->title,
                    'summary' => $log->body,
                    'occurred_at' => $log->created_at?->toIso8601String(),
                ]);
        }

        return $feed->concat($logs)->sortByDesc(fn ($row) => $row['occurred_at'] ?? '')->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function financials(User $user): array
    {
        $quests = Quest::query()
            ->with('questCategory:id,name')
            ->where(fn (Builder $q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->latest()
            ->limit(80)
            ->get();

        $running = 0;
        $transactions = $quests->flatMap(function (Quest $quest) use ($user, &$running): array {
            $rows = [];
            if ($quest->client_id === $user->id && $quest->budget_amount_minor > 0) {
                $running -= (int) $quest->budget_amount_minor;
                $rows[] = $this->transactionRow($quest, 'Escrow funded', -((int) $quest->budget_amount_minor), $running, $quest->escrow_funded_at ?? $quest->created_at);
            }
            if ($quest->freelancer_id === $user->id && $quest->paid_out_minor > 0) {
                $running += (int) $quest->paid_out_minor;
                $rows[] = $this->transactionRow($quest, 'Payout released', (int) $quest->paid_out_minor, $running, $quest->completed_at ?? $quest->updated_at);
            }

            return $rows;
        })->values();

        return [
            'balance_label' => $this->money($running),
            'transactions' => $transactions,
        ];
    }

    private function transactionRow(Quest $quest, string $type, int $amount, int $running, mixed $date): array
    {
        return [
            'id' => $quest->id.'-'.$type,
            'date' => $date?->toIso8601String(),
            'type' => $type,
            'amount' => $this->money($amount),
            'amount_minor' => $amount,
            'running_balance' => $this->money($running),
            'contract' => $quest->title,
            'contract_id' => $quest->id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contracts(User $user): array
    {
        return Quest::query()
            ->with(['client:id,name,email', 'freelancer:id,name,email', 'questCategory:id,name'])
            ->where(fn (Builder $q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (Quest $quest) => [
                'id' => $quest->id,
                'title' => $quest->title,
                'status' => $this->enumValue($quest->status),
                'value' => $this->money((int) $quest->budget_amount_minor),
                'category' => $quest->questCategory?->name,
                'client' => $quest->client?->name,
                'freelancer' => $quest->freelancer?->name,
                'started_at' => $quest->acceptedOffer?->accepted_at?->toIso8601String(),
                'completed_at' => $quest->completed_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function disputes(User $user): array
    {
        return QuestDispute::query()
            ->with(['quest:id,title,client_id,freelancer_id', 'openedBy:id,name,email', 'rulingFavouredUser:id,name,email'])
            ->whereHas('quest', fn (Builder $q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (QuestDispute $dispute) => [
                'id' => $dispute->id,
                'uuid' => $dispute->uuid,
                'quest' => $dispute->quest?->title,
                'status' => $this->enumValue($dispute->status),
                'phase' => $this->enumValue($dispute->phase),
                'amount' => $this->money((int) $dispute->disputed_amount_minor),
                'opened_by' => $dispute->openedBy?->name,
                'outcome' => $dispute->resolution_outcome,
                'favoured_user' => $dispute->rulingFavouredUser?->name,
                'created_at' => $dispute->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function reviews(User $user): array
    {
        return Review::query()
            ->with(['reviewer:id,name,email', 'reviewee:id,name,email', 'quest:id,title'])
            ->where(fn (Builder $q) => $q->where('reviewer_id', $user->id)->orWhere('reviewee_id', $user->id))
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'direction' => $review->reviewer_id === $user->id ? 'given' : 'received',
                'rating' => $review->rating,
                'content' => $review->body ?? $review->comment ?? $review->content ?? null,
                'status' => $this->enumValue($review->status ?? null),
                'reviewer' => $review->reviewer?->name,
                'reviewee' => $review->reviewee?->name,
                'quest' => $review->quest?->title,
                'created_at' => $review->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function notes(User $user): array
    {
        return AdminUserNote::query()
            ->with('admin:id,name,email')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AdminUserNote $note) => [
                'id' => $note->id,
                'body' => $note->body,
                'context' => $note->context ?? [],
                'admin' => $note->admin?->name,
                'created_at' => $note->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function money(int $minor): string
    {
        $prefix = $minor < 0 ? '-' : '';

        return $prefix.'₦'.number_format(abs($minor) / 100, 2);
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \BackedEnum ? $value->value : (string) $value;
    }
}
