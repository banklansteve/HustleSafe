<?php

namespace App\Services\Admin\UserActivityPatrol;

use App\Enums\UserActivityAnomalyType;
use App\Enums\UserActivityPatrolStatus;
use App\Enums\UserActivityRiskLevel;
use App\Models\User;
use App\Models\UserActivityPatrolFlag;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class UserActivityPatrolService
{
    private const EXCLUDED_ROLE_SLUGS = ['admin', 'super_admin'];

    public function __construct(
        private readonly UserActivityPatrolAnomalyService $anomalies,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request, bool $isSuperAdmin): array
    {
        return [
            'listing' => $this->listing($request),
            'filter_options' => $this->filterOptions(),
            'quick_counts' => $this->quickCounts($request->user()),
            'is_super_admin' => $isSuperAdmin,
            'capabilities' => $this->capabilities($isSuperAdmin),
            'warning_templates' => $this->warningTemplates(),
            'message_templates' => $this->messageTemplates(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listing(Request $request): array
    {
        $query = $this->baseQuery($request);
        $sort = (string) $request->query('sort', 'detected_at');
        $dir = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'detected_at' => 'user_activity_patrol_flags.detected_at',
            'risk_level' => 'user_activity_patrol_flags.risk_score',
            'username' => 'users.username',
            'anomaly_type' => 'user_activity_patrol_flags.anomaly_type',
            'status' => 'user_activity_patrol_flags.status',
        ];
        $sortCol = $sortMap[$sort] ?? $sortMap['detected_at'];

        if ($sort === 'risk_level') {
            $query->orderBy('user_activity_patrol_flags.risk_score', $dir);
        } else {
            $query->orderBy($sortCol, $dir);
        }

        $perPage = min(100, max(15, $request->integer('per_page', 25)));
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'items' => collect($paginator->items())->map(fn (UserActivityPatrolFlag $f) => $this->flagRow($f))->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'anomaly_types' => collect(UserActivityAnomalyType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'category' => $t->category(),
            ])->values()->all(),
            'risk_levels' => collect(UserActivityRiskLevel::cases())->map(fn ($l) => [
                'value' => $l->value,
                'label' => $l->label(),
            ])->values()->all(),
            'statuses' => collect(UserActivityPatrolStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
            'tiers' => collect(range(1, 7))->map(fn ($t) => ['value' => $t, 'label' => 'Tier '.$t])->all(),
            'user_types' => [
                ['value' => 'client', 'label' => 'Client'],
                ['value' => 'freelancer', 'label' => 'Freelancer'],
                ['value' => 'both', 'label' => 'Both'],
            ],
            'date_ranges' => [
                ['value' => '24h', 'label' => 'Last 24h'],
                ['value' => '7d', 'label' => 'Last 7 days'],
                ['value' => '30d', 'label' => 'Last 30 days'],
                ['value' => 'custom', 'label' => 'Custom'],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function quickCounts(?User $staff): array
    {
        return [
            'critical_open' => $this->excludeStaffUsers(UserActivityPatrolFlag::query())
                ->where('risk_level', UserActivityRiskLevel::Critical->value)
                ->where('status', UserActivityPatrolStatus::Open->value)
                ->count(),
            'my_assigned' => $staff ? $this->excludeStaffUsers(UserActivityPatrolFlag::query())
                ->where('assigned_to_id', $staff->id)
                ->whereIn('status', [
                    UserActivityPatrolStatus::Open->value,
                    UserActivityPatrolStatus::UnderReview->value,
                    UserActivityPatrolStatus::Watchlisted->value,
                ])
                ->count() : 0,
            'needs_review' => $this->excludeStaffUsers(UserActivityPatrolFlag::query())
                ->where('status', UserActivityPatrolStatus::UnderReview->value)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function capabilities(bool $isSuperAdmin): array
    {
        return [
            'can_suspend' => $isSuperAdmin,
            'can_terminate' => $isSuperAdmin,
            'can_reverse_transaction' => $isSuperAdmin,
            'can_merge_accounts' => $isSuperAdmin,
            'can_impose_sanction' => $isSuperAdmin,
            'can_view_kyc_documents' => $isSuperAdmin,
            'max_suspension_hours' => $isSuperAdmin ? null : 72,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function warningTemplates(): array
    {
        return [
            ['value' => 'velocity', 'label' => 'Unusual proposal activity detected. Please ensure all proposals are genuine and comply with platform guidelines.'],
            ['value' => 'disputes', 'label' => 'Multiple disputes have been filed recently. Please review quality standards and delivery commitments.'],
            ['value' => 'off_platform', 'label' => 'Off-platform payment references were detected in your conversations. All payments must go through HustleSafe escrow.'],
            ['value' => 'verification', 'label' => 'We noticed inconsistencies in your verification documents. Please update or clarify your identity information.'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function messageTemplates(): array
    {
        return [
            ['value' => 'account_review', 'label' => 'Account under review'],
            ['value' => 'suspicious_activity', 'label' => 'Suspicious activity notice'],
            ['value' => 'policy_violation', 'label' => 'Policy violation follow-up'],
            ['value' => 'verification_request', 'label' => 'Verification clarification needed'],
        ];
    }

    private function baseQuery(Request $request): Builder
    {
        $query = UserActivityPatrolFlag::query()
            ->select('user_activity_patrol_flags.*')
            ->join('users', 'users.id', '=', 'user_activity_patrol_flags.user_id')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereNotIn('roles.slug', self::EXCLUDED_ROLE_SLUGS)
            ->with(['user:id,name,username,email,verification_tier,avatar_url', 'assignedTo:id,name']);

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function (Builder $q) use ($term): void {
                $q->where('users.username', 'like', "%{$term}%")
                    ->orWhere('users.name', 'like', "%{$term}%")
                    ->orWhere('users.email', 'like', "%{$term}%");
                if (is_numeric($term)) {
                    $q->orWhere('users.id', (int) $term);
                }
            });
        }

        if ($request->filled('anomaly_types')) {
            $types = (array) $request->query('anomaly_types');
            $query->whereIn('anomaly_type', $types);
        }

        if ($request->filled('risk_levels')) {
            $query->whereIn('risk_level', (array) $request->query('risk_levels'));
        }

        if ($request->filled('statuses')) {
            $query->whereIn('user_activity_patrol_flags.status', (array) $request->query('statuses'));
        } else {
            $query->whereIn('user_activity_patrol_flags.status', [
                UserActivityPatrolStatus::Open->value,
                UserActivityPatrolStatus::UnderReview->value,
                UserActivityPatrolStatus::Watchlisted->value,
            ]);
        }

        if ($request->filled('tiers')) {
            $query->whereIn('users.verification_tier', (array) $request->query('tiers'));
        }

        if ($request->filled('user_type')) {
            $type = (string) $request->query('user_type');
            if ($type === 'client') {
                $query->whereHas('user.questsAsClient');
            } elseif ($type === 'freelancer') {
                $query->whereHas('user.questOffers');
            }
        }

        [$from, $to] = $this->resolveDateRange($request);
        $query->whereBetween('user_activity_patrol_flags.detected_at', [$from, $to]);

        if ($request->query('quick') === 'critical') {
            $query->where('risk_level', UserActivityRiskLevel::Critical->value)
                ->where('status', UserActivityPatrolStatus::Open->value);
        } elseif ($request->query('quick') === 'mine' && $request->user()) {
            $query->where('assigned_to_id', $request->user()->id);
        } elseif ($request->query('quick') === 'needs_review') {
            $query->where('status', UserActivityPatrolStatus::UnderReview->value);
        }

        return $query;
    }

    /**
     * @param  Builder<UserActivityPatrolFlag>  $query
     * @return Builder<UserActivityPatrolFlag>
     */
    private function excludeStaffUsers(Builder $query): Builder
    {
        return $query->whereHas('user.role', fn (Builder $role) => $role->whereNotIn('slug', self::EXCLUDED_ROLE_SLUGS));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $preset = (string) $request->query('range', '30d');
        if ($preset === '24h') {
            return [now()->subDay(), now()];
        }
        if ($preset === '7d') {
            return [now()->subDays(7), now()];
        }
        if ($preset === 'custom' && $request->filled('from') && $request->filled('to')) {
            return [Carbon::parse($request->query('from'))->startOfDay(), Carbon::parse($request->query('to'))->endOfDay()];
        }

        return [now()->subDays(30), now()];
    }

    /**
     * @return array<string, mixed>
     */
    private function flagRow(UserActivityPatrolFlag $flag): array
    {
        $user = $flag->user;
        $type = UserActivityAnomalyType::tryFrom($flag->anomaly_type);

        return [
            'id' => $flag->id,
            'user_id' => $flag->user_id,
            'username' => $user?->username ?? '—',
            'fullname' => $user?->name ?? '—',
            'avatar_url' => $user?->avatar_url,
            'tier' => (int) ($user?->verification_tier ?? 0),
            'anomaly_type' => $flag->anomaly_type,
            'anomaly_label' => $type?->label() ?? Str::headline($flag->anomaly_type),
            'risk_level' => $flag->risk_level,
            'risk_score' => $flag->risk_score,
            'detected_at' => $flag->detected_at?->toIso8601String(),
            'detected_ago' => $flag->detected_at?->diffForHumans(),
            'summary' => $flag->summary,
            'status' => $flag->status,
            'status_label' => UserActivityPatrolStatus::tryFrom($flag->status)?->label() ?? $flag->status,
            'assigned_to' => $flag->assignedTo?->only(['id', 'name']),
            'meta' => $flag->meta,
        ];
    }
}
