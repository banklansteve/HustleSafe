<?php

namespace App\Services\Admin;

use App\Enums\FinancialEscrowRecordStatus;
use App\Models\FinancialEscrowRecord;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\Quest;
use App\Models\QuestBoostPayment;
use App\Models\State;
use App\Support\EscrowAutoReleasePolicy;
use App\Support\NgnMoney;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class FinancialHealthChartService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $ttl = (int) config('financial_health_dashboard.cache.charts_ttl_seconds', 3600);

        return Cache::remember($this->cacheKey($request), $ttl, fn () => $this->buildUncached($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUncached(Request $request): array
    {
        $context = $this->resolveContext($request);

        return [
            'meta' => [
                'grain' => $context['grain'],
                'grain_label' => config("financial_health_dashboard.chart_grain_presets.{$context['grain']}", ucfirst($context['grain'])),
                'state_id' => $context['state_id'],
                'state_label' => $context['state_label'],
                'from' => $context['from']->toDateString(),
                'to' => $context['to']->toDateString(),
                'range_label' => $context['range_label'],
            ],
            'granularity_presets' => $this->granularityPresets(),
            'escrow_funding' => $this->escrowFundingChart($context),
            'platform_fee' => $this->platformFeeChart($context),
            'quest_boost' => $this->questBoostChart($context),
            'premium_subscription' => $this->premiumSubscriptionChart($context),
            'overall_revenue' => $this->overallRevenueChart($context),
            'vat_collected' => $this->vatCollectedChart($context),
            'release_status' => $this->releaseStatusChart($context),
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function stateOptions(): array
    {
        return State::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (State $state) => ['key' => (string) $state->id, 'label' => $state->name])
            ->prepend(['key' => 'all', 'label' => 'All states'])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveContext(Request $request): array
    {
        $period = $this->resolvePeriodKey($request);
        [$from, $to] = $this->periodRange($period, $request);
        $grain = $this->resolveGrain($request);
        $stateId = $this->resolveStateId($request);
        $stateLabel = 'All states';

        if ($stateId !== null) {
            $stateLabel = State::query()->whereKey($stateId)->value('name') ?? 'State #'.$stateId;
        }

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'grain' => $grain,
            'state_id' => $stateId,
            'state_label' => $stateLabel,
            'range_label' => $this->periodLabel($period, $request),
            'buckets' => $this->timeBuckets($from, $to, $grain),
        ];
    }

    private function resolveGrain(Request $request): string
    {
        $grain = (string) $request->query('chart_grain', 'daily');

        return array_key_exists($grain, config('financial_health_dashboard.chart_grain_presets', []))
            ? $grain
            : 'daily';
    }

    private function resolveStateId(Request $request): ?int
    {
        $raw = $request->query('state_id', 'all');
        if ($raw === null || $raw === '' || $raw === 'all') {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * @return list<array{key: string, label: string, start: Carbon, end: Carbon}>
     */
    private function timeBuckets(Carbon $from, Carbon $to, string $grain): array
    {
        $buckets = [];

        if ($grain === 'monthly') {
            $cursor = $from->copy()->startOfMonth();
            while ($cursor <= $to) {
                $start = $cursor->copy()->max($from);
                $end = $cursor->copy()->endOfMonth()->min($to);
                $buckets[] = [
                    'key' => $cursor->format('Y-m'),
                    'label' => $cursor->format('M Y'),
                    'start' => $start,
                    'end' => $end,
                ];
                $cursor->addMonth()->startOfMonth();
            }

            return $buckets;
        }

        if ($grain === 'weekly') {
            $cursor = $from->copy()->startOfWeek();
            if ($cursor->lt($from)) {
                $cursor = $from->copy();
            }
            while ($cursor <= $to) {
                $start = $cursor->copy();
                $end = $cursor->copy()->endOfWeek()->min($to);
                $buckets[] = [
                    'key' => $start->toDateString(),
                    'label' => $start->format('j M'),
                    'start' => $start,
                    'end' => $end,
                ];
                $cursor = $end->copy()->addDay()->startOfDay();
            }

            return $buckets;
        }

        $cursor = $from->copy()->startOfDay();
        while ($cursor <= $to) {
            $buckets[] = [
                'key' => $cursor->toDateString(),
                'label' => $cursor->format('j M'),
                'start' => $cursor->copy()->startOfDay(),
                'end' => $cursor->copy()->endOfDay(),
            ];
            $cursor->addDay();
        }

        return $buckets;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function escrowFundingChart(array $context): array
    {
        $totalMinor = 0;
        $funded = [];
        $released = [];
        $netHeld = [];
        $runningHeld = (int) $this->escrowBaseQuery($context['state_id'])
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->where('funded_at', '<', $context['from'])
            ->sum('total_funded_minor');

        foreach ($context['buckets'] as $bucket) {
            $fundedMinor = (int) $this->escrowBaseQuery($context['state_id'])
                ->whereBetween('funded_at', [$bucket['start'], $bucket['end']])
                ->sum('total_funded_minor');
            $releasedMinor = (int) $this->escrowBaseQuery($context['state_id'])
                ->whereNotNull('released_at')
                ->whereBetween('released_at', [$bucket['start'], $bucket['end']])
                ->sum('freelancer_net_minor');

            $totalMinor += $fundedMinor;
            $funded[] = $this->chartMajor($fundedMinor);
            $released[] = $this->chartMajor($releasedMinor);
            $runningHeld += $fundedMinor - $releasedMinor;
            $netHeld[] = $this->chartMajor(max(0, $runningHeld));
        }

        return $this->chartPayload($context['buckets'], [
            ['name' => 'Total funded', 'key' => 'funded', 'data' => $funded],
            ['name' => 'Total released', 'key' => 'released', 'data' => $released],
            ['name' => 'Net held', 'key' => 'net_held', 'data' => $netHeld],
        ], $totalMinor);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function platformFeeChart(array $context): array
    {
        $totalMinor = 0;
        $data = [];

        foreach ($context['buckets'] as $bucket) {
            $minor = (int) $this->platformFeeBaseQuery($context['state_id'])
                ->whereBetween('fee_recognised_at', [$bucket['start'], $bucket['end']])
                ->sum('platform_fee_minor');
            $totalMinor += $minor;
            $data[] = $this->chartMajor($minor);
        }

        return $this->chartPayload($context['buckets'], [
            ['name' => 'Platform fees', 'key' => 'platform_fee', 'data' => $data],
        ], $totalMinor);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function questBoostChart(array $context): array
    {
        $totalMinor = 0;
        $data = [];

        foreach ($context['buckets'] as $bucket) {
            $minor = (int) $this->boostBaseQuery($context['state_id'])
                ->whereBetween('paid_at', [$bucket['start'], $bucket['end']])
                ->sum('amount_minor');
            $totalMinor += $minor;
            $data[] = $this->chartMajor($minor);
        }

        return $this->chartPayload($context['buckets'], [
            ['name' => 'Quest boosts', 'key' => 'quest_boost', 'data' => $data],
        ], $totalMinor);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function premiumSubscriptionChart(array $context): array
    {
        $totalMinor = 0;
        $data = [];

        foreach ($context['buckets'] as $bucket) {
            $minor = (int) $this->premiumBaseQuery($context['state_id'])
                ->whereBetween('paid_at', [$bucket['start'], $bucket['end']])
                ->sum('amount_minor');
            $totalMinor += $minor;
            $data[] = $this->chartMajor($minor);
        }

        return $this->chartPayload($context['buckets'], [
            ['name' => 'Premium subscriptions', 'key' => 'premium', 'data' => $data],
        ], $totalMinor);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function overallRevenueChart(array $context): array
    {
        $boost = [];
        $premium = [];
        $platform = [];
        $total = [];
        $totalMinor = 0;

        foreach ($context['buckets'] as $bucket) {
            $boostMinor = (int) $this->boostBaseQuery($context['state_id'])
                ->whereBetween('paid_at', [$bucket['start'], $bucket['end']])
                ->sum('amount_minor');
            $premiumMinor = (int) $this->premiumBaseQuery($context['state_id'])
                ->whereBetween('paid_at', [$bucket['start'], $bucket['end']])
                ->sum('amount_minor');
            $platformMinor = (int) $this->platformFeeBaseQuery($context['state_id'])
                ->whereBetween('fee_recognised_at', [$bucket['start'], $bucket['end']])
                ->sum('platform_fee_minor');
            $bucketTotal = $boostMinor + $premiumMinor + $platformMinor;
            $totalMinor += $bucketTotal;

            $boost[] = $this->chartMajor($boostMinor);
            $premium[] = $this->chartMajor($premiumMinor);
            $platform[] = $this->chartMajor($platformMinor);
            $total[] = $this->chartMajor($bucketTotal);
        }

        return $this->chartPayload($context['buckets'], [
            ['name' => 'Quest boosts', 'key' => 'quest_boost', 'data' => $boost],
            ['name' => 'Premium subscriptions', 'key' => 'premium', 'data' => $premium],
            ['name' => 'Platform fees', 'key' => 'platform_fee', 'data' => $platform],
            ['name' => 'Total revenue', 'key' => 'total', 'data' => $total],
        ], $totalMinor);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function vatCollectedChart(array $context): array
    {
        $totalMinor = 0;
        $data = [];

        foreach ($context['buckets'] as $bucket) {
            $minor = (int) $this->escrowBaseQuery($context['state_id'])
                ->whereBetween('funded_at', [$bucket['start'], $bucket['end']])
                ->sum('vat_minor');
            $totalMinor += $minor;
            $data[] = $this->chartMajor($minor);
        }

        return $this->chartPayload($context['buckets'], [
            ['name' => 'VAT collected', 'key' => 'vat', 'data' => $data],
        ], $totalMinor);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function releaseStatusChart(array $context): array
    {
        $monthStart = $context['from']->copy()->startOfMonth();
        $heldQuery = $this->escrowBaseQuery($context['state_id'])
            ->where('status', FinancialEscrowRecordStatus::Held->value);
        $held = (int) (clone $heldQuery)->sum('freelancer_net_minor');
        $released = (int) $this->escrowBaseQuery($context['state_id'])
            ->whereIn('status', [FinancialEscrowRecordStatus::Released->value, FinancialEscrowRecordStatus::PartiallyReleased->value])
            ->where('released_at', '>=', $monthStart)
            ->where('released_at', '<=', $context['to'])
            ->sum('freelancer_net_minor');
        $disputed = (int) $this->escrowBaseQuery($context['state_id'])
            ->where('status', FinancialEscrowRecordStatus::Disputed->value)
            ->sum('freelancer_net_minor');

        $overdue = (int) (clone $heldQuery)->with('contract:id,agreed_delivery_date')->get()
            ->filter(function (FinancialEscrowRecord $r): bool {
                $due = $r->contract?->agreed_delivery_date;
                if (! $due) {
                    return false;
                }

                return now()->gt(EscrowAutoReleasePolicy::releaseAt(Carbon::parse($due)->endOfDay()));
            })
            ->sum('freelancer_net_minor');

        $segments = [
            ['key' => 'released', 'label' => 'Released', 'minor' => $released, 'color' => '#059669'],
            ['key' => 'awaiting', 'label' => 'Awaiting approval', 'minor' => max(0, $held - $overdue), 'color' => '#eab308'],
            ['key' => 'on_hold', 'label' => 'On hold', 'minor' => $disputed, 'color' => '#f97316'],
            ['key' => 'overdue', 'label' => 'Overdue', 'minor' => $overdue, 'color' => '#dc2626'],
        ];

        $segmentTotal = max(1, array_sum(array_column($segments, 'minor')));

        return [
            'center_minor' => $released,
            'center_display' => NgnMoney::format($released),
            'center_label' => 'Released in period',
            'segments' => collect($segments)->map(fn ($s) => [
                ...$s,
                'display' => NgnMoney::format($s['minor']),
                'percent' => round(($s['minor'] / $segmentTotal) * 100, 1),
            ])->values()->all(),
            'series' => collect($segments)->pluck('minor')->map(fn ($m) => $this->chartMajor($m))->all(),
            'labels' => collect($segments)->pluck('label')->all(),
            'colors' => collect($segments)->pluck('color')->all(),
        ];
    }

    /**
     * @param  list<array{key: string, label: string, start: Carbon, end: Carbon}>  $buckets
     * @param  list<array{name: string, key: string, data: list<int|float>}>  $series
     * @return array<string, mixed>
     */
    private function chartPayload(array $buckets, array $series, int $totalMinor): array
    {
        return [
            'labels' => collect($buckets)->pluck('label')->all(),
            'series' => $series,
            'total_minor' => $totalMinor,
            'total_display' => NgnMoney::format($totalMinor),
        ];
    }

    private function chartMajor(int $minor): int
    {
        return (int) round($minor / 100);
    }

    private function escrowBaseQuery(?int $stateId)
    {
        return FinancialEscrowRecord::query()->when($stateId, function ($query) use ($stateId): void {
            $query->whereIn('quest_id', Quest::query()->where('state_id', $stateId)->select('id'));
        });
    }

    private function platformFeeBaseQuery(?int $stateId)
    {
        return FinancialEscrowRecord::query()
            ->whereNotNull('fee_recognised_at')
            ->when($stateId, function ($query) use ($stateId): void {
                $query->whereIn('quest_id', Quest::query()->where('state_id', $stateId)->select('id'));
            });
    }

    private function boostBaseQuery(?int $stateId)
    {
        return QuestBoostPayment::query()
            ->where('status', 'paid')
            ->when($stateId, function ($query) use ($stateId): void {
                $query->whereIn('quest_id', Quest::query()->where('state_id', $stateId)->select('id'));
            });
    }

    private function premiumBaseQuery(?int $stateId)
    {
        return FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->when($stateId, function ($query) use ($stateId): void {
                $query->whereIn('user_id', function ($sub) use ($stateId): void {
                    $sub->select('id')->from('users')->where('state_id', $stateId);
                });
            });
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function granularityPresets(): array
    {
        return collect(config('financial_health_dashboard.chart_grain_presets', []))
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    private function resolvePeriodKey(Request $request): string
    {
        $key = (string) $request->query('period', 'today');

        if ($key === 'custom' && $request->filled('date_from') && $request->filled('date_to')) {
            return 'custom';
        }

        return array_key_exists($key, config('financial_health_dashboard.period_presets', [])) ? $key : 'today';
    }

    private function periodLabel(string $period, Request $request): string
    {
        if ($period === 'custom') {
            return $request->query('date_from').' to '.$request->query('date_to');
        }

        return config("financial_health_dashboard.period_presets.{$period}", ucfirst($period));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(string $period, ?Request $request = null): array
    {
        $now = now();

        if ($period === 'custom' && $request?->filled('date_from') && $request->filled('date_to')) {
            return [
                Carbon::parse($request->query('date_from'))->startOfDay(),
                Carbon::parse($request->query('date_to'))->endOfDay(),
            ];
        }

        return match ($period) {
            'week' => [$now->copy()->startOfWeek(), $now],
            'month' => [$now->copy()->startOfMonth(), $now],
            default => [$now->copy()->startOfDay(), $now],
        };
    }

    private function cacheKey(Request $request): string
    {
        $period = $this->resolvePeriodKey($request);
        $grain = $this->resolveGrain($request);
        $state = $request->query('state_id', 'all');

        return sprintf(
            'financial_health_dashboard:charts:v4:%s:%s:%s:%s:%s',
            $period,
            $request->query('date_from', ''),
            $request->query('date_to', ''),
            $grain,
            $state,
        );
    }
}
