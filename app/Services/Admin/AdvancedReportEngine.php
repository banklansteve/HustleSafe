<?php

namespace App\Services\Admin;

use App\Models\AdminSavedReport;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\State;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AdvancedReportEngine
{
    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return [
            'presets' => [
                ['key' => 'today', 'label' => 'Today'],
                ['key' => 'last_7_days', 'label' => 'Last 7 days'],
                ['key' => 'last_30_days', 'label' => 'Last 30 days'],
                ['key' => 'last_quarter', 'label' => 'Last quarter'],
                ['key' => 'last_year', 'label' => 'Last year'],
                ['key' => 'custom', 'label' => 'Custom'],
            ],
            'templates' => [
                ['key' => 'freelancer_performance', 'label' => 'Freelancer Performance Summary', 'description' => 'Daily or weekly freelancer performance, earnings, completion, ratings, disputes, and proposal success.'],
                ['key' => 'client_spend', 'label' => 'Client Spend Summary', 'description' => 'Client spend, average job value, repeat hire patterns, and category spend distribution.'],
                ['key' => 'category_health', 'label' => 'Category Health Summary', 'description' => 'Category strength, proposal volume, completion, hire ratio, budget, and freelancer availability.'],
                ['key' => 'revenue_distribution', 'label' => 'Revenue Distribution Summary', 'description' => 'Commission, boosts, subscription placeholder, category revenue, and segment attribution.'],
                ['key' => 'proposal_funnel', 'label' => 'Proposal Funnel Summary', 'description' => 'Proposal sent, viewed, shortlisted, accepted, and completed conversion intelligence.'],
                ['key' => 'geographic_distribution', 'label' => 'Geographic Distribution Summary', 'description' => 'Freelancer supply, client demand, jobs, and spend by Nigerian state and LGA.'],
                ['key' => 'platform_activity', 'label' => 'Platform Activity Summary', 'description' => 'Daily platform-wide health: users, activity, jobs, messages, and escrow movement.'],
            ],
            'grains' => [
                ['key' => 'daily', 'label' => 'Daily'],
                ['key' => 'weekly', 'label' => 'Weekly'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function landingStats(): array
    {
        [$from, $to] = $this->dateRange('last_30_days');
        $previousFrom = $from->subDays($from->diffInDays($to) + 1);
        $previousTo = $from->subSecond();

        return [
            'gmv' => $this->statWithChange(fn ($a, $b) => (int) Quest::query()->whereBetween('created_at', [$a, $b])->sum('paid_out_minor'), $from, $to, $previousFrom, $previousTo, 'money'),
            'active_contracts' => $this->statWithChange(fn ($a, $b) => Quest::query()->whereBetween('created_at', [$a, $b])->whereIn('status', ['assigned', 'in_progress'])->count(), $from, $to, $previousFrom, $previousTo),
            'platform_revenue_month' => $this->statWithChange(fn ($a, $b) => (int) round(((int) Quest::query()->whereBetween('created_at', [$a, $b])->sum('paid_out_minor')) * 0.1), now()->startOfMonth()->toImmutable(), now()->toImmutable(), now()->subMonth()->startOfMonth()->toImmutable(), now()->subMonth()->endOfMonth()->toImmutable(), 'money'),
            'new_users_month' => $this->statWithChange(fn ($a, $b) => User::query()->whereBetween('created_at', [$a, $b])->count(), now()->startOfMonth()->toImmutable(), now()->toImmutable(), now()->subMonth()->startOfMonth()->toImmutable(), now()->subMonth()->endOfMonth()->toImmutable()),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function run(array $payload): array
    {
        $template = (string) ($payload['report_type'] ?? $payload['template'] ?? 'freelancer_performance');
        $filters = (array) ($payload['filters'] ?? []);
        $page = max(1, (int) ($payload['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($payload['per_page'] ?? 25)));
        $sort = (string) ($payload['sort'] ?? '');
        $direction = strtolower((string) ($payload['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $grain = (string) ($payload['grain'] ?? 'daily');
        [$from, $to] = $this->dateRange((string) ($payload['date_preset'] ?? 'last_30_days'), $payload['date_from'] ?? null, $payload['date_to'] ?? null);

        return match ($template) {
            'client_spend' => $this->clientSpend($from, $to, $filters, $page, $perPage, $sort, $direction, $grain),
            'category_health' => $this->categoryHealth($from, $to, $filters, $page, $perPage, $sort, $direction),
            'revenue_distribution', 'revenue_attribution' => $this->revenueDistribution($from, $to, $filters, $page, $perPage, $sort, $direction),
            'proposal_funnel' => $this->proposalFunnel($from, $to, $filters, $page, $perPage, $sort, $direction),
            'geographic_distribution' => $this->geographicDistribution($from, $to, $filters, $page, $perPage, $sort, $direction),
            'platform_activity' => $this->platformActivity($from, $to, $filters, $page, $perPage, $grain),
            default => $this->freelancerPerformance($from, $to, $filters, $page, $perPage, $sort, $direction, $grain),
        };
    }

    public function savedReportsForUi(): array
    {
        return AdminSavedReport::query()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AdminSavedReport $report) => [
                'id' => $report->id,
                'name' => $report->name,
                'description' => $report->description,
                'report_type' => $report->report_type,
                'date_preset' => $report->date_preset,
                'last_run_at' => $report->last_run_at?->toIso8601String(),
                'schedule_frequency' => $report->schedule_frequency,
                'schedule_recipients' => $report->schedule_recipients ?? [],
                'builder_config' => $report->builder_config ?? [],
                'filters' => $report->filters ?? [],
            ])
            ->all();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dateRange(string $preset, mixed $from = null, mixed $to = null): array
    {
        $now = now()->toImmutable();

        return match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'last_quarter' => [$now->subMonths(3)->startOfDay(), $now->endOfDay()],
            'last_year' => [$now->subYear()->startOfDay(), $now->endOfDay()],
            'custom' => [
                CarbonImmutable::parse($from ?: $now->subDays(29))->startOfDay(),
                CarbonImmutable::parse($to ?: $now)->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
    }

    private function freelancerPerformance(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $page, int $perPage, string $sort, string $direction, string $grain): array
    {
        $base = DB::table('admin_report_user_daily_metrics as m')
            ->where('m.user_type', 'freelancer')
            ->whereBetween('m.metric_date', [$from->toDateString(), $to->toDateString()]);
        $this->applyAggregateFilters($base, $filters, 'm', hasUser: true);

        $totals = (clone $base)
            ->selectRaw('sum(jobs_completed) as jobs_completed, sum(jobs_disputed) as jobs_disputed, sum(proposals_sent) as proposals_sent, sum(proposals_accepted) as proposals_accepted, sum(earnings_minor) as earnings_minor, sum(rating_sum) as rating_sum, sum(rating_count) as rating_count')
            ->first();

        $timeline = $this->timeline((clone $base), $grain, [
            'jobs_completed' => 'sum(m.jobs_completed)',
            'earnings' => 'sum(m.earnings_minor)',
            'rating' => 'sum(m.rating_sum) / nullif(sum(m.rating_count), 0)',
        ]);

        $table = (clone $base)
            ->join('users', 'users.id', '=', 'm.user_id')
            ->select('m.user_id', 'users.name', 'users.email')
            ->selectRaw('sum(m.jobs_completed) as jobs_completed, sum(m.jobs_disputed) as disputes')
            ->selectRaw('round(100 * sum(m.jobs_completed) / nullif(sum(m.proposals_accepted), 0), 1) as completion_rate')
            ->selectRaw('round(100 * sum(m.jobs_disputed) / nullif(sum(m.jobs_completed), 0), 1) as dispute_rate')
            ->selectRaw('round(sum(m.rating_sum) / nullif(sum(m.rating_count), 0), 2) as average_rating')
            ->selectRaw('sum(m.earnings_minor) as earnings_minor')
            ->selectRaw('round(100 * sum(m.proposals_accepted) / nullif(sum(m.proposals_sent), 0), 1) as proposal_success_rate')
            ->groupBy('m.user_id', 'users.name', 'users.email');
        $this->applyUserSearch($table, $filters);
        $this->orderOrDefault($table, $sort, $direction, 'earnings_minor');
        $paginator = $table->paginate($perPage, ['*'], 'page', $page);

        $top = (clone $base)
            ->join('users', 'users.id', '=', 'm.user_id')
            ->select('users.name')
            ->selectRaw('sum(m.earnings_minor) as earnings_minor')
            ->groupBy('m.user_id', 'users.name')
            ->orderByDesc('earnings_minor')
            ->limit(10)
            ->get();

        return $this->response('Freelancer Performance Summary', 'freelancer_performance', $from, $to, $paginator, [
            ['label' => 'Jobs completed', 'value' => number_format((int) ($totals->jobs_completed ?? 0))],
            ['label' => 'Completion rate', 'value' => $this->percent((int) ($totals->jobs_completed ?? 0), (int) ($totals->proposals_accepted ?? 0))],
            ['label' => 'Average rating', 'value' => $this->average((float) ($totals->rating_sum ?? 0), (int) ($totals->rating_count ?? 0))],
            ['label' => 'Total earnings', 'value' => $this->money((int) ($totals->earnings_minor ?? 0))],
        ], [
            $this->lineChart('Performance over time', $timeline->pluck('period'), [
                ['name' => 'Jobs completed', 'data' => $timeline->pluck('jobs_completed')],
                ['name' => 'Average rating', 'data' => $timeline->pluck('rating')->map(fn ($v) => round((float) $v, 2))],
            ]),
            $this->barChart('Top freelancers by earnings', $top->pluck('name'), [['name' => 'Earnings', 'data' => $top->pluck('earnings_minor')->map(fn ($v) => round(((int) $v) / 100, 2))]]),
            $this->heatmapChart('Activity by day of week', $this->dayOfWeekSeries((clone $base), 'jobs_completed')),
        ]);
    }

    private function clientSpend(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $page, int $perPage, string $sort, string $direction, string $grain): array
    {
        $base = DB::table('admin_report_user_daily_metrics as m')
            ->where('m.user_type', 'client')
            ->whereBetween('m.metric_date', [$from->toDateString(), $to->toDateString()]);
        $this->applyAggregateFilters($base, $filters, 'm', hasUser: true);

        $totals = (clone $base)
            ->selectRaw('sum(spend_minor) as spend_minor, sum(jobs_completed) as jobs_completed, count(distinct user_id) as clients')
            ->first();
        $timeline = $this->timeline((clone $base), $grain, ['spend' => 'sum(m.spend_minor)', 'jobs' => 'sum(m.jobs_completed)']);

        $table = (clone $base)
            ->join('users', 'users.id', '=', 'm.user_id')
            ->select('m.user_id', 'users.name', 'users.email')
            ->selectRaw('sum(m.spend_minor) as total_spend')
            ->selectRaw('round(sum(m.spend_minor) / nullif(sum(m.jobs_completed), 0), 0) as average_spend_per_job')
            ->selectRaw('sum(m.jobs_completed) as jobs_completed')
            ->selectRaw('round(100 * (sum(m.jobs_completed) - count(distinct m.category_id)) / nullif(sum(m.jobs_completed), 0), 1) as repeat_hire_rate')
            ->groupBy('m.user_id', 'users.name', 'users.email');
        $this->applyUserSearch($table, $filters);
        $this->orderOrDefault($table, $sort, $direction, 'total_spend');
        $paginator = $table->paginate($perPage, ['*'], 'page', $page);

        $categorySpend = (clone $base)
            ->leftJoin('quest_categories', 'quest_categories.id', '=', 'm.category_id')
            ->selectRaw('coalesce(quest_categories.name, "Uncategorised") as category, sum(m.spend_minor) as spend_minor')
            ->groupBy('category')
            ->orderByDesc('spend_minor')
            ->limit(10)
            ->get();

        $top = collect($paginator->items())->take(10);

        return $this->response('Client Spend Summary', 'client_spend', $from, $to, $paginator, [
            ['label' => 'Total spend', 'value' => $this->money((int) ($totals->spend_minor ?? 0))],
            ['label' => 'Average spend per job', 'value' => $this->money($this->ratio((int) ($totals->spend_minor ?? 0), (int) ($totals->jobs_completed ?? 0)))],
            ['label' => 'Completed jobs', 'value' => number_format((int) ($totals->jobs_completed ?? 0))],
            ['label' => 'Clients analysed', 'value' => number_format((int) ($totals->clients ?? 0))],
        ], [
            $this->donutChart('Spend by category', $categorySpend->pluck('category'), $categorySpend->pluck('spend_minor')->map(fn ($v) => round(((int) $v) / 100, 2))),
            $this->lineChart('Client spend trend', $timeline->pluck('period'), [['name' => 'Spend', 'data' => $timeline->pluck('spend')->map(fn ($v) => round(((int) $v) / 100, 2))]]),
            $this->barChart('Top spending clients', $top->pluck('name'), [['name' => 'Spend', 'data' => $top->pluck('total_spend')->map(fn ($v) => round(((int) $v) / 100, 2))]]),
        ]);
    }

    private function categoryHealth(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $page, int $perPage, string $sort, string $direction): array
    {
        $base = DB::table('admin_report_category_daily_metrics as m')
            ->whereBetween('m.metric_date', [$from->toDateString(), $to->toDateString()]);
        $this->applyAggregateFilters($base, $filters, 'm');

        $table = (clone $base)
            ->leftJoin('quest_categories', 'quest_categories.id', '=', 'm.category_id')
            ->selectRaw('m.category_id, coalesce(quest_categories.name, "Uncategorised") as category')
            ->selectRaw('sum(m.jobs_posted) as jobs_posted, sum(m.jobs_completed) as jobs_completed, sum(m.proposal_volume) as proposal_volume, sum(m.hires) as hires')
            ->selectRaw('round(sum(m.budget_sum_minor) / nullif(sum(m.jobs_posted), 0), 0) as average_budget')
            ->selectRaw('round(sum(m.proposal_volume) / nullif(sum(m.hires), 0), 2) as proposal_to_hire_ratio')
            ->selectRaw('sum(m.freelancer_availability) as freelancer_availability')
            ->groupBy('m.category_id', 'category');
        $this->orderOrDefault($table, $sort, $direction, 'jobs_posted');
        $paginator = $table->paginate($perPage, ['*'], 'page', $page);
        $rows = collect($paginator->items());
        $totals = $rows->reduce(fn ($carry, $row) => [
            'jobs_posted' => $carry['jobs_posted'] + (int) $row->jobs_posted,
            'jobs_completed' => $carry['jobs_completed'] + (int) $row->jobs_completed,
            'proposal_volume' => $carry['proposal_volume'] + (int) $row->proposal_volume,
            'hires' => $carry['hires'] + (int) $row->hires,
        ], ['jobs_posted' => 0, 'jobs_completed' => 0, 'proposal_volume' => 0, 'hires' => 0]);

        return $this->response('Category Health Summary', 'category_health', $from, $to, $paginator, [
            ['label' => 'Jobs posted', 'value' => number_format($totals['jobs_posted'])],
            ['label' => 'Jobs completed', 'value' => number_format($totals['jobs_completed'])],
            ['label' => 'Proposal volume', 'value' => number_format($totals['proposal_volume'])],
            ['label' => 'Proposal-to-hire', 'value' => number_format($this->ratio($totals['proposal_volume'], $totals['hires']), 2).'x'],
        ], [
            $this->radarChart('Category strength profile', ['Jobs', 'Completion', 'Budget', 'Proposals', 'Availability'], $this->categoryRadar($rows->first())),
            $this->barChart('Jobs per category', $rows->pluck('category'), [['name' => 'Jobs posted', 'data' => $rows->pluck('jobs_posted')]]),
            $this->funnelChart('Proposal → hire → completion', [
                ['stage' => 'Proposals', 'count' => $totals['proposal_volume']],
                ['stage' => 'Hires', 'count' => $totals['hires']],
                ['stage' => 'Completed', 'count' => $totals['jobs_completed']],
            ]),
        ]);
    }

    private function revenueDistribution(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $page, int $perPage, string $sort, string $direction): array
    {
        $base = DB::table('admin_report_revenue_daily_metrics as m')
            ->whereBetween('m.metric_date', [$from->toDateString(), $to->toDateString()]);
        $this->applyAggregateFilters($base, $filters, 'm', hasLocalGovernment: false);

        $table = (clone $base)
            ->leftJoin('quest_categories', 'quest_categories.id', '=', 'm.category_id')
            ->selectRaw('m.fee_type, coalesce(quest_categories.name, "All categories") as category, m.user_segment')
            ->selectRaw('sum(m.revenue_minor) as revenue_minor')
            ->groupBy('m.fee_type', 'category', 'm.user_segment');
        $this->orderOrDefault($table, $sort, $direction, 'revenue_minor');
        $paginator = $table->paginate($perPage, ['*'], 'page', $page);
        $rows = collect($paginator->items());

        $monthly = (clone $base)
            ->selectRaw('date_format(m.metric_date, "%Y-%m") as period, m.fee_type, sum(m.revenue_minor) as revenue_minor')
            ->groupBy('period', 'm.fee_type')
            ->orderBy('period')
            ->get();
        $feeTotals = (clone $base)
            ->selectRaw('m.fee_type, sum(m.revenue_minor) as revenue_minor')
            ->groupBy('m.fee_type')
            ->orderByDesc('revenue_minor')
            ->get();

        return $this->response('Revenue Distribution Summary', 'revenue_distribution', $from, $to, $paginator, [
            ['label' => 'Total revenue', 'value' => $this->money((int) $feeTotals->sum('revenue_minor'))],
            ['label' => 'Commission', 'value' => $this->money((int) ($feeTotals->firstWhere('fee_type', 'commission')->revenue_minor ?? 0))],
            ['label' => 'Boosts', 'value' => $this->money((int) ($feeTotals->firstWhere('fee_type', 'boosts')->revenue_minor ?? 0))],
            ['label' => 'Subscriptions', 'value' => $this->money((int) ($feeTotals->firstWhere('fee_type', 'subscription')->revenue_minor ?? 0))],
        ], [
            $this->stackedBarChart('Revenue by source', $monthly),
            $this->lineChart('Monthly revenue trend', $monthly->pluck('period')->unique()->values(), [['name' => 'Revenue', 'data' => $monthly->groupBy('period')->map(fn ($items) => round(((int) $items->sum('revenue_minor')) / 100, 2))->values()]]),
            $this->donutChart('Revenue distribution', $feeTotals->pluck('fee_type')->map(fn ($v) => str($v)->headline()->toString()), $feeTotals->pluck('revenue_minor')->map(fn ($v) => round(((int) $v) / 100, 2))),
        ]);
    }

    private function proposalFunnel(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $page, int $perPage, string $sort, string $direction): array
    {
        $base = DB::table('admin_report_user_daily_metrics as m')
            ->where('m.user_type', 'freelancer')
            ->whereBetween('m.metric_date', [$from->toDateString(), $to->toDateString()]);
        $this->applyAggregateFilters($base, $filters, 'm', hasUser: true);

        $totals = (clone $base)
            ->selectRaw('sum(proposals_sent) as sent, sum(proposals_viewed) as viewed, sum(proposals_shortlisted) as shortlisted, sum(proposals_accepted) as accepted, sum(jobs_completed) as completed')
            ->first();

        $category = (clone $base)
            ->leftJoin('quest_categories', 'quest_categories.id', '=', 'm.category_id')
            ->selectRaw('coalesce(quest_categories.name, "Uncategorised") as category, sum(m.proposals_sent) as sent, sum(m.proposals_accepted) as accepted, sum(m.jobs_completed) as completed')
            ->groupBy('category')
            ->orderByDesc('sent');
        $this->orderOrDefault($category, $sort, $direction, 'sent');
        $paginator = $category->paginate($perPage, ['*'], 'page', $page);
        $rows = collect($paginator->items());
        $timeline = $this->timeline((clone $base), 'daily', ['sent' => 'sum(m.proposals_sent)', 'accepted' => 'sum(m.proposals_accepted)']);

        return $this->response('Proposal Funnel Summary', 'proposal_funnel', $from, $to, $paginator, [
            ['label' => 'Proposals sent', 'value' => number_format((int) ($totals->sent ?? 0))],
            ['label' => 'Viewed', 'value' => number_format((int) ($totals->viewed ?? 0))],
            ['label' => 'Accepted', 'value' => number_format((int) ($totals->accepted ?? 0))],
            ['label' => 'Completed', 'value' => number_format((int) ($totals->completed ?? 0))],
        ], [
            $this->funnelChart('Proposal → hire → completion', [
                ['stage' => 'Sent', 'count' => (int) ($totals->sent ?? 0)],
                ['stage' => 'Viewed', 'count' => (int) ($totals->viewed ?? 0)],
                ['stage' => 'Shortlisted', 'count' => (int) ($totals->shortlisted ?? 0)],
                ['stage' => 'Accepted', 'count' => (int) ($totals->accepted ?? 0)],
                ['stage' => 'Completed', 'count' => (int) ($totals->completed ?? 0)],
            ]),
            $this->lineChart('Conversion rate trend', $timeline->pluck('period'), [['name' => 'Acceptance rate', 'data' => $timeline->map(fn ($row) => round($this->ratio((int) $row->accepted, (int) $row->sent) * 100, 1))->values()]]),
            $this->barChart('Conversion by category', $rows->pluck('category'), [['name' => 'Accepted', 'data' => $rows->pluck('accepted')], ['name' => 'Completed', 'data' => $rows->pluck('completed')]]),
        ]);
    }

    private function geographicDistribution(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $page, int $perPage, string $sort, string $direction): array
    {
        $base = DB::table('admin_report_location_daily_metrics as m')
            ->whereBetween('m.metric_date', [$from->toDateString(), $to->toDateString()]);
        $this->applyLocationFilters($base, $filters, 'm');

        $table = (clone $base)
            ->leftJoin('states', 'states.id', '=', 'm.state_id')
            ->leftJoin('local_governments', 'local_governments.id', '=', 'm.local_government_id')
            ->selectRaw('m.state_id, m.local_government_id, coalesce(states.name, "Unknown") as state, coalesce(local_governments.name, "All LGAs") as local_government')
            ->selectRaw('sum(m.freelancers) as freelancers, sum(m.clients) as clients, sum(m.jobs_posted) as jobs_posted, sum(m.spend_minor) as spend_minor')
            ->groupBy('m.state_id', 'm.local_government_id', 'state', 'local_government');
        $this->orderOrDefault($table, $sort, $direction, 'jobs_posted');
        $paginator = $table->paginate($perPage, ['*'], 'page', $page);
        $rows = collect($paginator->items());

        return $this->response('Geographic Distribution Summary', 'geographic_distribution', $from, $to, $paginator, [
            ['label' => 'Freelancers', 'value' => number_format((int) $rows->sum('freelancers'))],
            ['label' => 'Clients', 'value' => number_format((int) $rows->sum('clients'))],
            ['label' => 'Jobs posted', 'value' => number_format((int) $rows->sum('jobs_posted'))],
            ['label' => 'Spend', 'value' => $this->money((int) $rows->sum('spend_minor'))],
        ], [
            ['type' => 'choropleth', 'title' => 'Nigeria activity intensity', 'states' => $rows],
            $this->barChart('Top states by activity', $rows->pluck('state'), [['name' => 'Jobs', 'data' => $rows->pluck('jobs_posted')]]),
            ['type' => 'scatter', 'title' => 'Supply vs demand', 'series' => [['name' => 'States', 'data' => $rows->map(fn ($row) => ['x' => (int) $row->freelancers, 'y' => (int) $row->clients, 'name' => $row->state])->values()]]],
        ]);
    }

    private function platformActivity(CarbonImmutable $from, CarbonImmutable $to, array $filters, int $page, int $perPage, string $grain): array
    {
        $base = DB::table('admin_report_platform_daily_metrics as m')
            ->whereBetween('m.metric_date', [$from->toDateString(), $to->toDateString()]);
        $timeline = $this->timeline($base, $grain, [
            'new_users' => 'sum(m.new_users)',
            'active_users' => 'sum(m.active_users)',
            'jobs_posted' => 'sum(m.jobs_posted)',
            'jobs_completed' => 'sum(m.jobs_completed)',
            'messages_sent' => 'sum(m.messages_sent)',
            'escrow_funded' => 'sum(m.escrow_funded_minor)',
            'escrow_released' => 'sum(m.escrow_released_minor)',
        ]);
        $paginator = new LengthAwarePaginator($timeline->forPage($page, $perPage)->values(), $timeline->count(), $perPage, $page);

        return $this->response('Platform Activity Summary', 'platform_activity', $from, $to, $paginator, [
            ['label' => 'New users', 'value' => number_format((int) $timeline->sum('new_users'))],
            ['label' => 'Active users', 'value' => number_format((int) $timeline->sum('active_users'))],
            ['label' => 'Jobs posted', 'value' => number_format((int) $timeline->sum('jobs_posted'))],
            ['label' => 'Escrow released', 'value' => $this->money((int) $timeline->sum('escrow_released'))],
        ], [
            $this->lineChart('DAU / WAU / MAU', $timeline->pluck('period'), [['name' => 'Active users', 'data' => $timeline->pluck('active_users')], ['name' => 'New users', 'data' => $timeline->pluck('new_users')]]),
            $this->areaChart('Jobs over time', $timeline->pluck('period'), [['name' => 'Posted', 'data' => $timeline->pluck('jobs_posted')], ['name' => 'Completed', 'data' => $timeline->pluck('jobs_completed')]]),
            $this->barChart('Daily escrow volume', $timeline->pluck('period'), [['name' => 'Funded', 'data' => $timeline->pluck('escrow_funded')->map(fn ($v) => round(((int) $v) / 100, 2))], ['name' => 'Released', 'data' => $timeline->pluck('escrow_released')->map(fn ($v) => round(((int) $v) / 100, 2))]]),
        ]);
    }

    private function response(string $name, string $template, CarbonImmutable $from, CarbonImmutable $to, LengthAwarePaginator $paginator, array $summary, array $charts): array
    {
        $rows = collect($paginator->items())->map(fn ($row) => (array) $row)->values();

        return [
            'name' => $name,
            'template' => $template,
            'date_range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => $summary,
            'charts' => $charts,
            'columns' => $rows->first() ? array_keys($rows->first()) : [],
            'rows' => $this->formatRows($rows),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function applyAggregateFilters(
        Builder $query,
        array $filters,
        string $alias,
        bool $hasCategory = true,
        bool $hasLocation = true,
        bool $hasLocalGovernment = true,
        bool $hasUser = false,
    ): void
    {
        if ($hasLocation && ! empty($filters['state_id'])) {
            $query->where("{$alias}.state_id", (int) $filters['state_id']);
        }
        if ($hasLocation && $hasLocalGovernment && ! empty($filters['local_government_id'])) {
            $query->where("{$alias}.local_government_id", (int) $filters['local_government_id']);
        }
        if ($hasCategory && ! empty($filters['category_id'])) {
            $query->where("{$alias}.category_id", (int) $filters['category_id']);
        }
        if ($hasUser && ! empty($filters['user_id'])) {
            $query->where("{$alias}.user_id", (int) $filters['user_id']);
        }
    }

    private function applyLocationFilters(Builder $query, array $filters, string $alias): void
    {
        if (! empty($filters['state_id'])) {
            $query->where("{$alias}.state_id", (int) $filters['state_id']);
        }
        if (! empty($filters['local_government_id'])) {
            $query->where("{$alias}.local_government_id", (int) $filters['local_government_id']);
        }
    }

    private function applyUserSearch(Builder $query, array $filters): void
    {
        if (! empty($filters['user_search'])) {
            $search = '%'.str_replace('%', '', (string) $filters['user_search']).'%';
            $query->where(fn ($q) => $q->where('users.name', 'like', $search)->orWhere('users.email', 'like', $search));
        }
    }

    private function timeline(Builder $query, string $grain, array $selects): Collection
    {
        $periodExpr = $grain === 'weekly'
            ? 'date_format(m.metric_date, "%x-W%v")'
            : 'date(m.metric_date)';

        $query->selectRaw("{$periodExpr} as period");
        foreach ($selects as $alias => $expr) {
            $query->selectRaw("coalesce({$expr}, 0) as {$alias}");
        }

        return $query->groupBy('period')->orderBy('period')->get();
    }

    private function dayOfWeekSeries(Builder $query, string $metric): array
    {
        return $query
            ->selectRaw('dayname(m.metric_date) as day, sum(m.'.$metric.') as value')
            ->groupBy('day')
            ->get()
            ->map(fn ($row) => ['x' => $row->day, 'y' => (int) $row->value])
            ->values()
            ->all();
    }

    private function stackedBarChart(string $title, Collection $rows): array
    {
        $labels = $rows->pluck('period')->unique()->values();
        $series = $rows->pluck('fee_type')->unique()->values()->map(fn ($feeType) => [
            'name' => str($feeType)->headline()->toString(),
            'data' => $labels->map(fn ($period) => round(((int) ($rows->first(fn ($row) => $row->period === $period && $row->fee_type === $feeType)->revenue_minor ?? 0)) / 100, 2))->values(),
        ])->values();

        return ['type' => 'stacked_bar', 'title' => $title, 'labels' => $labels, 'series' => $series];
    }

    private function lineChart(string $title, Collection $labels, array $series): array
    {
        return ['type' => 'line', 'title' => $title, 'labels' => $labels->values(), 'series' => $series];
    }

    private function areaChart(string $title, Collection $labels, array $series): array
    {
        return ['type' => 'area', 'title' => $title, 'labels' => $labels->values(), 'series' => $series];
    }

    private function barChart(string $title, Collection $labels, array $series): array
    {
        return ['type' => 'bar', 'title' => $title, 'labels' => $labels->values(), 'series' => $series];
    }

    private function donutChart(string $title, Collection $labels, Collection $series): array
    {
        return ['type' => 'donut', 'title' => $title, 'labels' => $labels->values(), 'series' => $series->values()];
    }

    private function radarChart(string $title, array $labels, array $data): array
    {
        return ['type' => 'radar', 'title' => $title, 'labels' => $labels, 'series' => [['name' => 'Strength', 'data' => $data]]];
    }

    private function funnelChart(string $title, array $series): array
    {
        $first = max(1, (int) ($series[0]['count'] ?? 0));

        return [
            'type' => 'funnel',
            'title' => $title,
            'series' => collect($series)->map(fn ($row) => [
                ...$row,
                'conversion_rate' => round(((int) $row['count'] / $first) * 100, 1),
            ])->values(),
        ];
    }

    private function heatmapChart(string $title, array $data): array
    {
        return ['type' => 'heatmap', 'title' => $title, 'series' => [['name' => 'Activity', 'data' => $data]]];
    }

    private function categoryRadar(mixed $row): array
    {
        if (! $row) {
            return [0, 0, 0, 0, 0];
        }

        return [
            min(100, (int) $row->jobs_posted),
            min(100, (int) $row->jobs_completed),
            min(100, round(((int) $row->average_budget) / 100000)),
            min(100, (int) $row->proposal_volume),
            min(100, (int) $row->freelancer_availability),
        ];
    }

    private function orderOrDefault(Builder $query, string $sort, string $direction, string $default): void
    {
        $query->orderBy($sort !== '' ? $sort : $default, $direction);
    }

    private function formatRows(Collection $rows): array
    {
        return $rows->map(function (array $row) {
            foreach ($row as $key => $value) {
                if (is_numeric($value) && (str_contains($key, 'minor') || str_contains($key, 'spend') || str_contains($key, 'earnings') || str_contains($key, 'budget') || str_contains($key, 'revenue'))) {
                    $row[$key] = $this->money((int) $value);
                }
            }

            return $row;
        })->all();
    }

    private function statWithChange(callable $callback, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo, string $format = 'number'): array
    {
        $value = (int) $callback($from, $to);
        $previous = (int) $callback($previousFrom, $previousTo);

        return [
            'value' => $format === 'money' ? $this->money($value) : number_format($value),
            'raw' => $value,
            'change_pct' => $previous > 0 ? round((($value - $previous) / $previous) * 100, 1) : ($value > 0 ? 100 : 0),
        ];
    }

    private function money(int $minor): string
    {
        return '₦'.number_format($minor / 100, 2);
    }

    private function percent(int $part, int $whole): string
    {
        return $whole > 0 ? number_format(($part / $whole) * 100, 1).'%' : '0%';
    }

    private function ratio(int $part, int $whole): int|float
    {
        return $whole > 0 ? $part / $whole : 0;
    }

    private function average(float $sum, int $count): string
    {
        return $count > 0 ? number_format($sum / $count, 2) : '0.00';
    }
}
