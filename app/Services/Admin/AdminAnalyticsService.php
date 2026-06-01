<?php

namespace App\Services\Admin;

use App\Enums\LedgerAccount;
use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Enums\UserVerificationStatus;
use App\Models\ContentReport;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\State;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\LedgerEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminAnalyticsService
{
    /**
     * @return array<string, int|float|string>
     */
    public function kpiSnapshot(): array
    {
        $disputesTotal = QuestDispute::query()->count();
        $resolvedTotal = QuestDispute::query()->whereNotNull('resolved_at')->count();

        $posted = Quest::query()->count();
        $completed = Quest::query()->where('status', QuestStatus::Completed)->count();

        return [
            'users_total' => User::query()->count(),
            'users_new_30d' => User::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'quests_posted' => $posted,
            'quests_open' => Quest::query()->where('status', QuestStatus::Open)->count(),
            'quests_in_progress' => Quest::query()->where('status', QuestStatus::InProgress)->count(),
            'quests_completed' => $completed,
            'completion_rate_pct' => $posted > 0 ? round(($completed / $posted) * 100, 1) : 0,
            'disputes_open' => QuestDispute::query()->whereIn('status', [
                QuestDisputeStatus::Open,
                QuestDisputeStatus::SelfResolving,
                QuestDisputeStatus::Escalated,
                QuestDisputeStatus::AwaitingRuling,
            ])->count(),
            'dispute_resolution_rate_pct' => $disputesTotal > 0
                ? round(($resolvedTotal / $disputesTotal) * 100, 1)
                : 0,
            'escrow_held_minor' => (int) Quest::query()
                ->where('escrow_status', 'funded')
                ->sum('budget_amount_minor'),
            'paid_out_minor' => (int) Quest::query()->sum('paid_out_minor'),
            'verification_queue' => UserVerification::query()
                ->whereIn('status', [UserVerificationStatus::Pending, UserVerificationStatus::InReview])
                ->count(),
            'open_reports' => ContentReport::query()->where('status', 'open')->count(),
            'proposals_total' => QuestOffer::query()->count(),
            'proposals_hired' => Quest::query()->whereNotNull('accepted_quest_offer_id')->count(),
        ];
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function signupsSeries(string $tz, int $days = 14): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now($tz)->startOfDay()->subDays($i)->toDateString();
            $out[] = [
                'date' => $day,
                'count' => User::query()->whereDate('created_at', $day)->count(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{date: string, minor: int}>
     */
    public function escrowHeldDailySeries(string $tz, int $days = 14): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now($tz)->startOfDay()->subDays($i);
            $end = $day->copy()->endOfDay();
            $minor = (int) Quest::query()
                ->where('escrow_status', 'funded')
                ->where('escrow_funded_at', '<=', $end)
                ->where(function ($q) use ($end): void {
                    $q->whereNull('completed_at')->orWhere('completed_at', '>', $end);
                })
                ->sum('budget_amount_minor');
            $out[] = [
                'date' => $day->toDateString(),
                'minor' => $minor,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    public function questMix(): array
    {
        return Quest::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status instanceof QuestStatus ? $row->status->value : (string) $row->status,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{category: string, count: int, revenue_minor: int}>
     */
    public function categoryHeatmap(): array
    {
        $rows = Quest::query()
            ->select(
                'quest_category_id',
                DB::raw('count(*) as quest_count'),
                DB::raw('coalesce(sum(paid_out_minor), 0) as revenue_minor'),
            )
            ->whereNotNull('quest_category_id')
            ->groupBy('quest_category_id')
            ->orderByDesc('quest_count')
            ->limit(24)
            ->get();

        $names = QuestCategory::query()
            ->whereIn('id', $rows->pluck('quest_category_id'))
            ->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'category' => $names[$row->quest_category_id] ?? '—',
            'count' => (int) $row->quest_count,
            'revenue_minor' => (int) $row->revenue_minor,
        ])->values()->all();
    }

    /**
     * @return list<array{step: string, count: int}>
     */
    public function proposalFunnel(): array
    {
        $offers = QuestOffer::query()->count();
        $shortlisted = QuestOffer::query()->whereNotNull('shortlisted_at')->count();
        $accepted = Quest::query()->whereNotNull('accepted_quest_offer_id')->count();
        $completed = Quest::query()->where('status', QuestStatus::Completed)->count();

        return [
            ['step' => 'Proposals', 'count' => $offers],
            ['step' => 'Shortlisted', 'count' => $shortlisted],
            ['step' => 'Hired', 'count' => $accepted],
            ['step' => 'Completed', 'count' => $completed],
        ];
    }

    /**
     * @return list<array{state: string, count: int}>
     */
    public function geographicDistribution(): array
    {
        $rows = Quest::query()
            ->select('state_id', DB::raw('count(*) as aggregate'))
            ->whereNotNull('state_id')
            ->groupBy('state_id')
            ->orderByDesc('aggregate')
            ->limit(37)
            ->get();

        $names = State::query()->whereIn('id', $rows->pluck('state_id'))->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'state' => $names[$row->state_id] ?? '—',
            'count' => (int) $row->aggregate,
        ])->values()->all();
    }

    /**
     * @return list<array{month: string, retained_pct: float}>
     */
    public function cohortRetention(): array
    {
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $end = $start->copy()->endOfMonth();
            $cohort = User::query()->whereBetween('created_at', [$start, $end])->pluck('id');
            $cohortSize = $cohort->count();
            if ($cohortSize === 0) {
                $out[] = ['month' => $start->format('M Y'), 'retained_pct' => 0];

                continue;
            }
            $returned = User::query()
                ->whereIn('id', $cohort)
                ->where('last_active_at', '>=', $end->copy()->addDays(7))
                ->count();
            $out[] = [
                'month' => $start->format('M Y'),
                'retained_pct' => round(($returned / $cohortSize) * 100, 1),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{name: string, email: string, metric: int|float, label: string}>
     */
    public function topFreelancers(int $limit = 8): array
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))
            ->with('trustMetrics')
            ->get(['id', 'name', 'email', 'slug'])
            ->sortByDesc(fn (User $u) => (int) ($u->trust_score ?? 0))
            ->take($limit)
            ->values()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'slug' => $u->slug,
                'metric' => (int) ($u->trust_score ?? 0),
                'label' => 'Trust score',
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, email: string, metric: int, label: string}>
     */
    public function topClients(int $limit = 8): array
    {
        return User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.slug')
            ->selectRaw('coalesce(sum(quests.paid_out_minor), 0) as spend_minor')
            ->leftJoin('quests', 'quests.client_id', '=', 'users.id')
            ->whereHas('role', fn ($q) => $q->where('slug', 'client'))
            ->groupBy('users.id', 'users.name', 'users.email', 'users.slug')
            ->orderByDesc('spend_minor')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'slug' => $row->slug,
                'metric' => (int) $row->spend_minor,
                'label' => 'Escrow released (minor)',
            ])
            ->all();
    }

    /**
     * Revenue & accounting charts for Reports & analytics (financial audit scope only).
     *
     * @return array<string, mixed>
     */
    public function financialReportsCharts(): array
    {
        $tz = config('app.timezone');

        return [
            'escrow_daily' => $this->escrowHeldDailySeries($tz, 30),
            'platform_fee_daily' => $this->ledgerAccountDailySeries(LedgerAccount::PlatformFeeRevenue->value, $tz, 30),
            'vat_daily' => $this->ledgerAccountDailySeries(LedgerAccount::VatPayable->value, $tz, 30),
            'escrow_inflow_daily' => $this->ledgerEventDailyInflowSeries($tz, 30),
        ];
    }

    /**
     * Marketplace activity charts for the Insights page.
     *
     * @return array<string, mixed>
     */
    public function operationalInsightsCharts(): array
    {
        $tz = config('app.timezone');

        return [
            'signups' => $this->signupsSeries($tz),
            'quest_mix' => $this->questMix(),
            'category_heatmap' => $this->categoryHeatmap(),
            'funnel' => $this->proposalFunnel(),
            'geo' => $this->geographicDistribution(),
            'cohort' => $this->cohortRetention(),
        ];
    }

    /**
     * @return list<array{date: string, minor: int}>
     */
    public function ledgerAccountDailySeries(string $account, string $tz, int $days = 30): array
    {
        if (! Schema::hasTable('ledger_entries')) {
            return [];
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now($tz)->startOfDay()->subDays($i);
            $dayEnd = $day->copy()->endOfDay();
            $minor = (int) LedgerEntry::query()
                ->where('ledger_account', $account)
                ->where('side', 'credit')
                ->whereBetween('occurred_at', [$day, $dayEnd])
                ->sum('amount_minor');

            $out[] = [
                'date' => $day->toDateString(),
                'minor' => $minor,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{date: string, minor: int}>
     */
    public function ledgerEventDailyInflowSeries(string $tz, int $days = 30): array
    {
        if (! Schema::hasTable('ledger_entries')) {
            return $this->escrowHeldDailySeries($tz, $days);
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now($tz)->startOfDay()->subDays($i);
            $dayEnd = $day->copy()->endOfDay();
            $minor = (int) LedgerEntry::query()
                ->where('ledger_account', LedgerAccount::ClientEscrowLiability->value)
                ->where('side', 'credit')
                ->whereBetween('occurred_at', [$day, $dayEnd])
                ->sum('amount_minor');

            $out[] = [
                'date' => $day->toDateString(),
                'minor' => $minor,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardPayload(): array
    {
        $tz = config('app.timezone');

        return [
            'kpi' => $this->kpiSnapshot(),
            'charts' => [
                ...$this->operationalInsightsCharts(),
                ...$this->financialReportsCharts(),
            ],
            'leaderboards' => [
                'freelancers' => $this->topFreelancers(),
                'clients' => $this->topClients(),
            ],
            'generated_at' => Carbon::now($tz)->toIso8601String(),
        ];
    }
}
