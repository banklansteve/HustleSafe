<?php

namespace App\Services\Admin\PremiumPatrol;

use App\Enums\FreelancerSubscriptionStatus;
use App\Enums\FreelancerSubscriptionTier;
use App\Enums\PremiumPatrolFlagStatus;
use App\Enums\PremiumPatrolFlagType;
use App\Enums\PremiumPatrolSubjectType;
use App\Enums\QuestBoostStatus;
use App\Models\FreelancerSubscription;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\PremiumPatrolAction;
use App\Models\PremiumPatrolFlag;
use App\Models\PremiumPatrolInvestigation;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\QuestOffer;
use App\Models\User;
use App\Support\NgnMoney;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class PremiumPatrolService
{
    public function __construct(
        private readonly PremiumPatrolMetricsService $metrics,
        private readonly PremiumPatrolAnomalyService $anomalies,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request): array
    {
        [$from, $to, $rangePreset] = $this->resolveDateRange($request);

        return [
            'tab' => $request->query('tab', 'overview'),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'preset' => $rangePreset],
            'metrics' => $this->metrics->dashboardPayload($from, $to),
            'anomaly_alerts' => $this->openAnomalyAlerts(),
            'premium_users' => $this->premiumUsersTable($request),
            'boosted_quests' => $this->boostedQuestsTable($request),
            'filter_options' => $this->filterOptions(),
            'reason_codes' => $this->reasonCodes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function premiumUserDetail(User $user): array
    {
        return app(PremiumPatrolUserDetailService::class)->build($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function boostDetail(QuestBoost $boost): array
    {
        $boost->load(['quest.questCategory', 'quest.client', 'client']);
        $quest = $boost->quest;

        $median = 0;
        if ($quest?->quest_category_id) {
            $medianQuest = Quest::query()
                ->where('quest_category_id', $quest->quest_category_id)
                ->where('budget_amount_minor', '>', 0)
                ->orderBy('budget_amount_minor')
                ->skip((int) floor(Quest::query()->where('quest_category_id', $quest->quest_category_id)->count() / 2))
                ->value('budget_amount_minor');
            $median = (int) ($medianQuest ?? 0);
        }

        $budgetMinor = (int) ($quest?->budget_amount_minor ?? 0);
        $deviationPct = $median > 0 && $budgetMinor > 0
            ? round((($budgetMinor - $median) / $median) * 100, 1)
            : null;

        $flags = $this->flagsFor(PremiumPatrolSubjectType::BoostedQuest, $boost->id);
        $proposalsCount = QuestOffer::query()->where('quest_id', $boost->quest_id)->count();

        return [
            'boost' => $this->boostRow($boost, $flags, $deviationPct, $proposalsCount),
            'budget_vs_market' => [
                'budget_display' => NgnMoney::format($budgetMinor),
                'market_median_display' => NgnMoney::format($median),
                'deviation_percent' => $deviationPct,
            ],
            'client_account_age_days' => $boost->client?->created_at?->diffInDays($boost->granted_at ?? now()),
            'flags' => $flags,
            'actions' => PremiumPatrolAction::query()
                ->where('subject_type', PremiumPatrolSubjectType::BoostedQuest->value)
                ->where('subject_id', $boost->id)
                ->orderByDesc('occurred_at')
                ->limit(30)
                ->with('actor:id,name,email')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'action_type' => $a->action_type,
                    'actor' => $a->actor?->only(['id', 'name', 'email']),
                    'reason_notes' => $a->reason_notes,
                    'occurred_at' => $a->occurred_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metricsApi(Request $request): array
    {
        [$from, $to] = $this->resolveDateRange($request);

        return $this->metrics->dashboardPayload($from, $to);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openAnomalyAlerts(): array
    {
        return PremiumPatrolFlag::query()
            ->where('status', PremiumPatrolFlagStatus::Open->value)
            ->orderByRaw("FIELD(severity, 'high', 'medium', 'low')")
            ->orderByDesc('detected_at')
            ->limit(15)
            ->get()
            ->map(fn (PremiumPatrolFlag $f) => [
                'id' => $f->id,
                'flag_type' => $f->flag_type,
                'label' => PremiumPatrolFlagType::from($f->flag_type)->label(),
                'severity' => $f->severity,
                'message' => $this->flagMessage($f),
                'subject_type' => $f->subject_type,
                'subject_id' => $f->subject_id,
                'detected_at' => $f->detected_at?->toIso8601String(),
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function premiumUsersTable(Request $request): array
    {
        $query = FreelancerSubscription::query()
            ->where('tier', FreelancerSubscriptionTier::Pro->value)
            ->with(['user.stateModel'])
            ->whereNotNull('started_at');

        if ($signup = $request->query('signup_range')) {
            $since = match ($signup) {
                '24h' => now()->subDay(),
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                default => null,
            };
            if ($since) {
                $query->where('started_at', '>=', $since);
            }
        }

        if ($status = $request->query('patrol_status')) {
            if ($status === 'suspended') {
                $query->where('status', FreelancerSubscriptionStatus::AdminSuspended->value);
            } elseif ($status === 'active') {
                $query->where('status', FreelancerSubscriptionStatus::Active->value);
            } elseif ($status === 'flagged') {
                $flaggedIds = PremiumPatrolFlag::query()
                    ->where('subject_type', PremiumPatrolSubjectType::PremiumUser->value)
                    ->where('status', PremiumPatrolFlagStatus::Open->value)
                    ->pluck('subject_id');
                $query->whereIn('user_id', $flaggedIds);
            }
        }

        if ($tier = $request->query('verification_tier')) {
            $query->whereHas('user', fn (Builder $q) => $q->where('verification_tier', (int) $tier));
        }

        if ($billing = $request->query('billing_cycle')) {
            $query->where('billing_cycle', $billing);
        }

        $sort = $request->query('sort', 'recent');
        if ($sort === 'risk') {
            $query->orderByDesc('started_at');
        } else {
            $query->orderByDesc('started_at');
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate(25)->withQueryString();

        $rows = $paginator->getCollection()->map(function (FreelancerSubscription $sub) {
            $user = $sub->user;
            $flags = $user ? $this->flagsFor(PremiumPatrolSubjectType::PremiumUser, $user->id) : [];
            $payment = FreelancerSubscriptionPayment::query()
                ->where('user_id', $sub->user_id)
                ->where('status', 'paid')
                ->orderByDesc('paid_at')
                ->first();
            $accountAge = $payment?->paid_at && $user
                ? $user->created_at->diffInDays($payment->paid_at)
                : null;

            return $this->userRow($user, $sub, $flags, $accountAge, $payment);
        })->sortByDesc(fn ($row) => ($row['risk_score'] * 1000) + ($row['has_open_flags'] ? 500 : 0))->values();

        $paginator->setCollection($rows);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boostedQuestsTable(Request $request): array
    {
        $query = QuestBoost::query()->with(['quest.questCategory', 'client']);

        if ($status = $request->query('boost_status')) {
            if ($status === 'active') {
                $query->activeNow();
            } elseif ($status === 'expired') {
                $query->where('status', QuestBoostStatus::Expired->value);
            } else {
                $query->where('status', $status);
            }
        } else {
            $query->orderByDesc('granted_at');
        }

        if ($duration = $request->query('duration')) {
            $query->where('tier', $duration);
        }

        if ($from = $request->query('boost_from')) {
            $query->where('granted_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to = $request->query('boost_to')) {
            $query->where('granted_at', '<=', Carbon::parse($to)->endOfDay());
        }

        if ($request->query('budget_outlier') === 'high') {
            $query->whereHas('quest', fn (Builder $q) => $q->where('budget_amount_minor', '>', 0));
        }

        $paginator = $query->paginate(25)->withQueryString();

        $rows = $paginator->getCollection()->map(function (QuestBoost $boost) {
            $flags = $this->flagsFor(PremiumPatrolSubjectType::BoostedQuest, $boost->id);
            $proposals = QuestOffer::query()->where('quest_id', $boost->quest_id)->count();
            $deviation = null;
            $quest = $boost->quest;
            if ($quest?->budget_amount_minor && $quest->quest_category_id) {
                $median = Quest::query()
                    ->where('quest_category_id', $quest->quest_category_id)
                    ->where('budget_amount_minor', '>', 0)
                    ->avg('budget_amount_minor');
                if ($median > 0) {
                    $deviation = round((($quest->budget_amount_minor - $median) / $median) * 100, 1);
                }
            }

            return $this->boostRow($boost, $flags, $deviation, $proposals);
        })->sortByDesc(fn ($row) => ($row['risk_score'] * 1000) + ($row['has_open_flags'] ? 500 : 0))->values();

        $paginator->setCollection($rows);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $flags
     * @return array<string, mixed>
     */
    private function userRow(?User $user, ?FreelancerSubscription $sub, array $flags, ?int $accountAgeAtPurchase, ?FreelancerSubscriptionPayment $payment = null): array
    {
        $riskScore = $this->riskScoreFromFlags($flags, $accountAgeAtPurchase);
        $patrolStatus = 'active';
        if ($sub?->status === FreelancerSubscriptionStatus::AdminSuspended->value) {
            $patrolStatus = 'suspended';
        } elseif (count(array_filter($flags, fn ($f) => ($f['status'] ?? '') === 'open')) > 0) {
            $patrolStatus = 'flagged';
        }

        return [
            'user_id' => $user?->id,
            'fullname' => $user?->name ?? '—',
            'signup_date' => $sub?->started_at?->toIso8601String(),
            'verification_tier' => (int) ($user?->verification_tier ?? 0),
            'subscription_type' => $sub?->billing_cycle === 'year' ? 'Annual' : 'Monthly',
            'cost_paid_display' => NgnMoney::format((int) ($payment?->amount_minor ?? $sub?->monthly_price_minor ?? 0)),
            'account_age_at_purchase' => $accountAgeAtPurchase !== null ? "{$accountAgeAtPurchase} days old" : '—',
            'account_age_flag' => $accountAgeAtPurchase !== null && $accountAgeAtPurchase < 7,
            'risk_score' => $riskScore,
            'trust_score' => (int) ($user?->trust_score ?? 0),
            'patrol_status' => $patrolStatus,
            'location' => trim(($user?->city ?? '').($user?->stateModel?->name ? ', '.$user->stateModel->name : '')) ?: '—',
            'flags' => $flags,
            'has_open_flags' => count(array_filter($flags, fn ($f) => ($f['status'] ?? '') === 'open')) > 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $flags
     * @return array<string, mixed>
     */
    private function boostRow(QuestBoost $boost, array $flags, ?float $deviationPct, int $proposalsCount): array
    {
        $quest = $boost->quest;
        $status = $boost->isActive() ? 'active' : ($boost->status === QuestBoostStatus::Expired->value ? 'expired' : $boost->status);

        return [
            'id' => $boost->id,
            'reference' => $boost->reference,
            'quest_id' => $boost->quest_id,
            'quest_title' => $boost->quest_title_snapshot ?? $quest?->title,
            'client_name' => $boost->client?->name ?? $quest?->client?->name ?? '—',
            'boost_start' => $boost->starts_at?->toIso8601String(),
            'duration_label' => $boost->tierEnum()->label(),
            'cost_display' => NgnMoney::format((int) $boost->planned_cost_minor),
            'job_value_display' => NgnMoney::format((int) ($quest?->budget_amount_minor ?? 0)),
            'client_tier' => (int) ($boost->client?->verification_tier ?? $quest?->client?->verification_tier ?? 0),
            'status' => $status,
            'proposals_count' => $proposalsCount,
            'budget_deviation_percent' => $deviationPct,
            'category' => $quest?->questCategory?->name ?? '—',
            'flags' => $flags,
            'risk_score' => $this->riskScoreFromFlags($flags),
            'has_open_flags' => count(array_filter($flags, fn ($f) => ($f['status'] ?? '') === 'open')) > 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flagsFor(PremiumPatrolSubjectType $type, int $subjectId): array
    {
        return PremiumPatrolFlag::query()
            ->where('subject_type', $type->value)
            ->where('subject_id', $subjectId)
            ->orderByDesc('detected_at')
            ->get()
            ->map(fn (PremiumPatrolFlag $f) => [
                'id' => $f->id,
                'flag_type' => $f->flag_type,
                'label' => PremiumPatrolFlagType::from($f->flag_type)->label(),
                'severity' => $f->severity,
                'status' => $f->status,
                'detected_at' => $f->detected_at?->toIso8601String(),
            ])->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $flags
     */
    private function riskScoreFromFlags(array $flags, ?int $accountAgeDays = null): int
    {
        $score = 0;
        foreach ($flags as $flag) {
            if (($flag['status'] ?? '') !== PremiumPatrolFlagStatus::Open->value) {
                continue;
            }
            $score += match ($flag['severity'] ?? 'medium') {
                'high' => 40,
                'medium' => 25,
                default => 10,
            };
        }
        if ($accountAgeDays !== null && $accountAgeDays < 7) {
            $score += 20;
        }

        return min(100, $score);
    }

    private function flagMessage(PremiumPatrolFlag $flag): string
    {
        $type = PremiumPatrolFlagType::from($flag->flag_type);
        $meta = $flag->meta ?? [];

        return match ($type) {
            PremiumPatrolFlagType::BulkPremiumFraud => sprintf(
                '%d premium signups from same IP in last %dh',
                (int) ($meta['count'] ?? 0),
                (int) ($meta['window_hours'] ?? 24),
            ),
            PremiumPatrolFlagType::BoostSpam => sprintf(
                'Client boosted %d quests in %dh',
                (int) ($meta['count'] ?? 0),
                (int) config('premium_patrol.boost_spam_hours', 48),
            ),
            default => $type->label(),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveDateRange(Request $request): array
    {
        $preset = (string) $request->query('range', '30d');
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now();
        $from = match ($preset) {
            '7d' => now()->subDays(7)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            'custom' => Carbon::parse($request->query('from', now()->subDays(30)))->startOfDay(),
            default => now()->subDays(30)->startOfDay(),
        };

        if ($preset === 'custom' && $request->query('from')) {
            $from = Carbon::parse($request->query('from'))->startOfDay();
        }

        return [$from, $to, $preset];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'billing_cycles' => [['value' => 'month', 'label' => 'Monthly'], ['value' => 'year', 'label' => 'Annual']],
            'boost_durations' => collect(\App\Enums\QuestBoostTier::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all(),
            'patrol_statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'suspended', 'label' => 'Suspended'],
                ['value' => 'flagged', 'label' => 'Flagged'],
            ],
        ];
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    private function reasonCodes(): array
    {
        return [
            'premium_suspend' => [
                ['value' => 'fraud', 'label' => 'Account flagged for fraud'],
                ['value' => 'suspicious', 'label' => 'Suspicious activity'],
                ['value' => 'terms', 'label' => 'Violation of terms'],
                ['value' => 'investigation', 'label' => 'Under investigation'],
            ],
            'boost_demote' => [
                ['value' => 'policy', 'label' => 'Violates platform policy'],
                ['value' => 'suspicious', 'label' => 'Suspicious activity'],
                ['value' => 'investigation', 'label' => 'Under investigation'],
                ['value' => 'quality', 'label' => 'Quality issue'],
            ],
        ];
    }
}
