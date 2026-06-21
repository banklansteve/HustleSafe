<?php

namespace App\Services\Admin;

use App\Enums\FreelancerSubscriptionStatus;
use App\Enums\FreelancerSubscriptionTier;
use App\Enums\QuestBoostStatus;
use App\Models\FinancialEscrowRecord;
use App\Models\FreelancerSubscription;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\LedgerEntry;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\QuestBoostPayment;
use App\Models\User;
use App\Support\NgnMoney;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RevenueMonitorService
{
    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request): array
    {
        [$from, $to, $preset, $label] = $this->resolveRange($request);
        [$prevFrom, $prevTo] = $this->previousPeriod($from, $to);

        $overview = $this->overview($from, $to, $prevFrom, $prevTo);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $label,
                'preset' => $preset,
            ],
            'presets' => $this->presets(),
            'overview' => $overview,
            'trend' => $this->dailyTrend($from, $to),
            'breakdown' => $this->breakdown($overview, $prevFrom, $prevTo),
            'cohort' => $this->cohortBars($from, $to),
            'sidebar' => $this->sidebarMetrics($from, $to, $prevFrom, $prevTo),
            'transactions' => $this->transactions($request, $from, $to),
            'trend_insights' => $this->trendInsights($from, $to),
            'top_spenders' => $this->topSpenders($from, $to),
        ];
    }

    /**
     * Top 10 paying users per revenue stream for the selected period.
     *
     * @return list<array{key: string, label: string, attribution: string, spenders: list<array<string, mixed>>}>
     */
    private function topSpenders(Carbon $from, Carbon $to): array
    {
        $boost = QuestBoostPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('client_id as user_id, SUM(amount_minor) as total, COUNT(*) as cnt')
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $premium = FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('user_id, SUM(amount_minor) as total, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $platform = FinancialEscrowRecord::query()
            ->whereNotNull('fee_recognised_at')
            ->whereBetween('fee_recognised_at', [$from, $to])
            ->whereNotNull('client_id')
            ->selectRaw('client_id as user_id, SUM(platform_fee_minor) as total, COUNT(*) as cnt')
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            ['key' => 'quest_boost', 'label' => 'Quest boosts', 'attribution' => 'Paying client', 'spenders' => $this->mapSpenders($boost)],
            ['key' => 'premium', 'label' => 'Premium members', 'attribution' => 'Subscriber', 'spenders' => $this->mapSpenders($premium)],
            ['key' => 'platform_fee', 'label' => 'Platform fees', 'attribution' => 'Funding client', 'spenders' => $this->mapSpenders($platform)],
        ];
    }

    /**
     * @param  Collection<int, object>  $rows  rows with user_id, total, cnt
     * @return list<array<string, mixed>>
     */
    private function mapSpenders(Collection $rows): array
    {
        $userIds = $rows->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name', 'username', 'avatar_url'])->keyBy('id');

        return $rows->map(function ($row) use ($users) {
            $userId = (int) $row->user_id;
            $user = $users->get($userId);
            $total = (int) $row->total;

            return [
                'user_id' => $userId,
                'name' => $user?->name ?? ('User #'.$userId),
                'username' => $user?->username,
                'avatar_url' => $user?->avatar_url,
                'total_minor' => $total,
                'total_display' => NgnMoney::format($total),
                'transactions' => (int) $row->cnt,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionDetail(string $type, int $id): array
    {
        return match ($type) {
            'premium' => $this->premiumDetail($id),
            'boost' => $this->boostDetail($id),
            'platform_fee' => $this->platformFeeDetail($id),
            default => abort(404),
        };
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $request->merge(['page' => 1, 'per_page' => 5000]);
        $rows = $this->transactions($request, $from, $to)['items'];

        $filename = 'revenue-monitor-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Revenue type', 'Transaction ID', 'Party', 'Date', 'Duration', 'Gross', 'Status', 'Net', 'Reference',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['revenue_type_label'],
                    $row['transaction_id'],
                    $row['party_label'],
                    $row['date'],
                    $row['duration_label'] ?? '',
                    NgnMoney::csvMajor((int) $row['amount_minor']),
                    $row['status_label'],
                    NgnMoney::csvMajor((int) $row['net_minor']),
                    $row['reference'] ?? '',
                ]);
            }
            fclose($out);
        }, $filename);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function presets(): array
    {
        return [
            ['key' => 'today', 'label' => 'Today'],
            ['key' => 'last_7_days', 'label' => 'Last 7 days'],
            ['key' => 'this_month', 'label' => 'This month'],
            ['key' => 'last_month', 'label' => 'Last month'],
            ['key' => 'last_3_months', 'label' => 'Last 3 months'],
            ['key' => 'last_6_months', 'label' => 'Last 6 months'],
            ['key' => 'this_year', 'label' => 'This year'],
            ['key' => 'custom', 'label' => 'Custom range'],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string}
     */
    private function resolveRange(Request $request): array
    {
        $preset = (string) $request->query('preset', 'this_month');
        $now = now();

        return match ($preset) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
                'today',
                'Today',
            ],
            'last_7_days' => [
                $now->copy()->subDays(6)->startOfDay(),
                $now->copy()->endOfDay(),
                'last_7_days',
                'Last 7 days',
            ],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
                'last_month',
                $now->copy()->subMonth()->format('F Y'),
            ],
            'last_3_months' => [
                $now->copy()->subMonths(3)->startOfDay(),
                $now->copy()->endOfDay(),
                'last_3_months',
                'Last 3 months',
            ],
            'last_6_months' => [
                $now->copy()->subMonths(6)->startOfDay(),
                $now->copy()->endOfDay(),
                'last_6_months',
                'Last 6 months',
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfDay(),
                'this_year',
                $now->format('Y'),
            ],
            'last_12_months' => [
                $now->copy()->subMonths(12)->startOfDay(),
                $now->copy()->endOfDay(),
                'last_12_months',
                'Last 12 months',
            ],
            'custom' => [
                Carbon::parse($request->query('from', $now->copy()->startOfMonth()->toDateString()))->startOfDay(),
                Carbon::parse($request->query('to', $now->toDateString()))->endOfDay(),
                'custom',
                'Custom range',
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfDay(),
                'this_month',
                $now->format('F Y'),
            ],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousPeriod(Carbon $from, Carbon $to): array
    {
        $days = max(1, $from->diffInDays($to) + 1);

        return [$from->copy()->subDays($days)->startOfDay(), $from->copy()->subDay()->endOfDay()];
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $premium = $this->sumPremium($from, $to);
        $boost = $this->sumBoost($from, $to);
        $platform = $this->sumPlatformFees($from, $to);
        $gross = $premium + $boost + $platform;

        $premiumRefunds = $this->sumPremiumRefunds($from, $to);
        $boostRefunds = $this->sumBoostRefunds($from, $to);
        $net = $gross - $this->processorFees($premium, $boost) - $premiumRefunds - $boostRefunds;

        $prevPremium = $this->sumPremium($prevFrom, $prevTo);
        $prevBoost = $this->sumBoost($prevFrom, $prevTo);
        $prevPlatform = $this->sumPlatformFees($prevFrom, $prevTo);
        $prevGross = $prevPremium + $prevBoost + $prevPlatform;

        $activePremium = FreelancerSubscription::query()
            ->where('tier', FreelancerSubscriptionTier::Pro->value)
            ->where('status', FreelancerSubscriptionStatus::Active->value)
            ->where(fn ($q) => $q->whereNull('renewal_date')->orWhere('renewal_date', '>', now()))
            ->count();

        $activeBoosts = QuestBoost::query()->activeNow()->count();

        return [
            'total_gross_minor' => $gross,
            'total_gross_display' => NgnMoney::format($gross),
            'total_net_minor' => max(0, $net),
            'total_net_display' => NgnMoney::format(max(0, $net)),
            'growth_percent' => $this->growthPercent($gross, $prevGross),
            'streams' => [
                [
                    'key' => 'quest_boost',
                    'label' => 'Quest boosts',
                    'amount_minor' => $boost,
                    'amount_display' => NgnMoney::format($boost),
                    'percent' => $gross > 0 ? round(($boost / $gross) * 100) : 0,
                    'growth_percent' => $this->growthPercent($boost, $prevBoost),
                ],
                [
                    'key' => 'premium',
                    'label' => 'Premium members',
                    'amount_minor' => $premium,
                    'amount_display' => NgnMoney::format($premium),
                    'percent' => $gross > 0 ? round(($premium / $gross) * 100) : 0,
                    'growth_percent' => $this->growthPercent($premium, $prevPremium),
                ],
                [
                    'key' => 'platform_fee',
                    'label' => 'Platform fees',
                    'amount_minor' => $platform,
                    'amount_display' => NgnMoney::format($platform),
                    'percent' => $gross > 0 ? round(($platform / $gross) * 100) : 0,
                    'growth_percent' => $this->growthPercent($platform, $prevPlatform),
                ],
            ],
            'active_premium_users' => $activePremium,
            'active_boosted_quests' => $activeBoosts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function breakdown(array $overview, Carbon $prevFrom, Carbon $prevTo): array
    {
        $series = collect($overview['streams'])->map(fn ($s) => [
            'key' => $s['key'],
            'label' => $s['label'],
            'amount_minor' => $s['amount_minor'],
            'percent' => $s['percent'],
            'growth_percent' => $s['growth_percent'],
        ])->values()->all();

        return ['series' => $series];
    }

    /**
     * @return array<string, mixed>
     */
    private function dailyTrend(Carbon $from, Carbon $to): array
    {
        $dates = collect();
        $cursor = $from->copy()->startOfDay();
        while ($cursor <= $to) {
            $dates->push($cursor->toDateString());
            $cursor->addDay();
        }

        $premiumByDay = $this->dailyPremium($from, $to);
        $boostByDay = $this->dailyBoost($from, $to);
        $platformByDay = $this->dailyPlatform($from, $to);

        return [
            'categories' => $dates->all(),
            'series' => [
                ['name' => 'Quest boosts', 'key' => 'quest_boost', 'data' => $dates->map(fn ($d) => (int) ($boostByDay[$d] ?? 0))->all()],
                ['name' => 'Premium revenue', 'key' => 'premium', 'data' => $dates->map(fn ($d) => (int) ($premiumByDay[$d] ?? 0))->all()],
                ['name' => 'Platform fees', 'key' => 'platform_fee', 'data' => $dates->map(fn ($d) => (int) ($platformByDay[$d] ?? 0))->all()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trendInsights(Carbon $from, Carbon $to): array
    {
        $premiumByDay = $this->dailyPremium($from, $to);
        $boostByDay = $this->dailyBoost($from, $to);
        $platformByDay = $this->dailyPlatform($from, $to);

        $totals = collect($premiumByDay)->keys()
            ->merge(collect($boostByDay)->keys())
            ->merge(collect($platformByDay)->keys())
            ->unique()
            ->mapWithKeys(fn ($date) => [
                $date => (int) ($premiumByDay[$date] ?? 0) + (int) ($boostByDay[$date] ?? 0) + (int) ($platformByDay[$date] ?? 0),
            ]);

        if ($totals->isEmpty()) {
            return [
                'daily_average_display' => NgnMoney::format(0),
                'peak_day' => null,
                'peak_amount_display' => NgnMoney::format(0),
                'trend_direction' => 'flat',
            ];
        }

        $days = max(1, $from->diffInDays($to) + 1);
        $peakDate = $totals->sortDesc()->keys()->first();
        $firstHalf = $totals->take(ceil($totals->count() / 2))->sum();
        $secondHalf = $totals->skip(ceil($totals->count() / 2))->sum();

        return [
            'daily_average_display' => NgnMoney::format((int) round($totals->sum() / $days)),
            'peak_day' => $peakDate,
            'peak_amount_display' => NgnMoney::format((int) $totals->max()),
            'trend_direction' => $secondHalf > $firstHalf ? 'up' : ($secondHalf < $firstHalf ? 'down' : 'flat'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cohortBars(Carbon $from, Carbon $to): array
    {
        $months = collect();
        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= $to) {
            $months->push($cursor->format('Y-m'));
            $cursor->addMonth();
        }

        $newPremium = FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, COUNT(DISTINCT user_id) as cnt")
            ->groupBy('month')
            ->pluck('cnt', 'month');

        $returningPremium = FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->whereIn('user_id', function ($q) use ($from): void {
                $q->select('user_id')
                    ->from('freelancer_subscription_payments')
                    ->where('status', 'paid')
                    ->where('paid_at', '<', $from);
            })
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, COUNT(DISTINCT user_id) as cnt")
            ->groupBy('month')
            ->pluck('cnt', 'month');

        return [
            'categories' => $months->map(fn ($m) => Carbon::parse($m.'-01')->format('M Y'))->all(),
            'series' => [
                ['name' => 'New premium payers', 'data' => $months->map(fn ($m) => (int) ($newPremium[$m] ?? 0))->all()],
                ['name' => 'Returning premium payers', 'data' => $months->map(fn ($m) => (int) ($returningPremium[$m] ?? 0))->all()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sidebarMetrics(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $premiumPaid = FreelancerSubscriptionPayment::query()->where('status', 'paid')->whereBetween('paid_at', [$from, $to]);
        $boostPaid = QuestBoostPayment::query()->where('status', 'paid')->whereBetween('paid_at', [$from, $to]);
        $platformCount = FinancialEscrowRecord::query()->whereNotNull('fee_recognised_at')->whereBetween('fee_recognised_at', [$from, $to]);

        $premiumCount = (clone $premiumPaid)->count();
        $boostCount = (clone $boostPaid)->count();
        $platformFeeCount = (clone $platformCount)->count();

        $premiumSum = (int) (clone $premiumPaid)->sum('amount_minor');
        $boostSum = (int) (clone $boostPaid)->sum('amount_minor');
        $platformSum = $this->sumPlatformFees($from, $to);

        $prevPremiumSum = $this->sumPremium($prevFrom, $prevTo);
        $prevBoostSum = $this->sumBoost($prevFrom, $prevTo);
        $prevPlatformSum = $this->sumPlatformFees($prevFrom, $prevTo);

        $premiumRefunds = (clone $premiumPaid)->whereIn('status', config('revenue_monitor.refund_statuses', []))->count();
        $boostRefunds = (clone $boostPaid)->whereIn('status', config('revenue_monitor.refund_statuses', []))->count();

        $avgBoostDays = QuestBoost::query()
            ->whereBetween('granted_at', [$from, $to])
            ->get()
            ->avg(fn (QuestBoost $b) => $b->starts_at->diffInDays($b->ends_at));

        $newPremiumUsers = FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->distinct('user_id')
            ->count('user_id');

        $churnBase = max(1, FreelancerSubscription::query()
            ->where('tier', FreelancerSubscriptionTier::Pro->value)
            ->where('started_at', '<', $from)
            ->count());
        $churned = FreelancerSubscription::query()
            ->whereIn('status', [FreelancerSubscriptionStatus::Cancelled->value, FreelancerSubscriptionStatus::Expired->value])
            ->whereBetween('cancelled_at', [$from, $to])
            ->count();

        return [
            'avg_transaction' => [
                'premium_display' => NgnMoney::format($premiumCount > 0 ? (int) round($premiumSum / $premiumCount) : 0),
                'boost_display' => NgnMoney::format($boostCount > 0 ? (int) round($boostSum / $boostCount) : 0),
                'platform_fee_display' => NgnMoney::format($platformFeeCount > 0 ? (int) round($platformSum / $platformFeeCount) : 0),
            ],
            'growth' => [
                'premium_percent' => $this->growthPercent($premiumSum, $prevPremiumSum),
                'boost_percent' => $this->growthPercent($boostSum, $prevBoostSum),
                'platform_percent' => $this->growthPercent($platformSum, $prevPlatformSum),
            ],
            'churn_rate_percent' => round(($churned / $churnBase) * 100, 1),
            'refund_rates' => [
                'premium_percent' => $premiumCount > 0 ? round(($premiumRefunds / $premiumCount) * 100, 1) : 0,
                'boost_percent' => $boostCount > 0 ? round(($boostRefunds / $boostCount) * 100, 1) : 0,
            ],
            'avg_boost_duration_days' => round((float) ($avgBoostDays ?? 0), 1),
            'new_premium_users' => $newPremiumUsers,
            'estimated_ltv_display' => NgnMoney::format($newPremiumUsers * ($premiumCount > 0 ? (int) round($premiumSum / max(1, $premiumCount)) : 0) * 13),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactions(Request $request, Carbon $from, Carbon $to): array
    {
        $typeFilter = (string) $request->query('revenue_type', '');
        $statusFilter = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));
        $minAmount = $request->filled('amount_min') ? (int) $request->integer('amount_min') : null;
        $maxAmount = $request->filled('amount_max') ? (int) $request->integer('amount_max') : null;
        $sort = (string) $request->query('sort', 'date');
        $dir = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $page = max(1, $request->integer('page', 1));
        $perPage = min(100, max(15, $request->integer('per_page', 25)));

        $rows = collect();

        if ($typeFilter === '' || $typeFilter === 'premium') {
            $rows = $rows->merge($this->premiumTransactionRows($from, $to));
        }
        if ($typeFilter === '' || $typeFilter === 'quest_boost') {
            $rows = $rows->merge($this->boostTransactionRows($from, $to));
        }
        if ($typeFilter === '' || $typeFilter === 'platform_fee') {
            $rows = $rows->merge($this->platformFeeTransactionRows($from, $to));
        }

        if ($statusFilter !== '') {
            $rows = $rows->filter(fn ($r) => $r['status'] === $statusFilter);
        }
        if ($search !== '') {
            $term = mb_strtolower($search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['party_label'] ?? ''), $term)
                || str_contains(mb_strtolower($r['reference'] ?? ''), $term)
                || str_contains((string) ($r['transaction_id'] ?? ''), $term));
        }
        if ($minAmount !== null) {
            $rows = $rows->filter(fn ($r) => (int) $r['amount_minor'] >= $minAmount);
        }
        if ($maxAmount !== null) {
            $rows = $rows->filter(fn ($r) => (int) $r['amount_minor'] <= $maxAmount);
        }

        $rows = $rows->sortBy([
            [$sort === 'amount' ? 'amount_minor' : ($sort === 'type' ? 'revenue_type' : 'occurred_at'), $dir === 'asc' ? SORT_ASC : SORT_DESC],
        ])->values();

        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function premiumTransactionRows(Carbon $from, Carbon $to): Collection
    {
        $feePct = config('revenue_monitor.processor_fee_percent.premium', 5);

        return FreelancerSubscriptionPayment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->whereIn('status', ['paid', ...config('revenue_monitor.refund_statuses', [])])
            ->with('user:id,name,username,verification_tier')
            ->orderByDesc('paid_at')
            ->limit(500)
            ->get()
            ->map(function (FreelancerSubscriptionPayment $p) use ($feePct) {
                $gross = (int) $p->amount_minor;
                $fee = (int) round($gross * ($feePct / 100));
                $net = $gross - $fee;

                return [
                    'id' => $p->id,
                    'revenue_type' => 'premium',
                    'revenue_type_label' => 'Premium',
                    'transaction_id' => 'PRE-'.$p->id,
                    'party_label' => $p->user?->name ?? ('User #'.$p->user_id),
                    'party_username' => $p->user?->username,
                    'party_user_id' => $p->user_id,
                    'date' => $p->paid_at?->toDateString(),
                    'occurred_at' => $p->paid_at?->toIso8601String(),
                    'duration_label' => $p->billing_cycle === 'year' ? 'Annual' : 'Monthly',
                    'amount_minor' => $gross,
                    'amount_display' => NgnMoney::format($gross),
                    'net_minor' => $net,
                    'net_display' => NgnMoney::format($net),
                    'status' => $p->status,
                    'status_label' => ucfirst($p->status),
                    'reference' => $p->paystack_reference,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function boostTransactionRows(Carbon $from, Carbon $to): Collection
    {
        $feePct = config('revenue_monitor.processor_fee_percent.quest_boost', 7);

        return QuestBoostPayment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->whereIn('status', ['paid', ...config('revenue_monitor.refund_statuses', [])])
            ->with(['client:id,name,username,verification_tier', 'quest:id,title,reference_code'])
            ->orderByDesc('paid_at')
            ->limit(500)
            ->get()
            ->map(function (QuestBoostPayment $p) use ($feePct) {
                $gross = (int) $p->amount_minor;
                $fee = (int) round($gross * ($feePct / 100));
                $net = $gross - $fee;

                return [
                    'id' => $p->id,
                    'revenue_type' => 'quest_boost',
                    'revenue_type_label' => 'Quest boost',
                    'transaction_id' => 'BST-'.$p->id,
                    'party_label' => $p->client?->name ?? ('Client #'.$p->client_id),
                    'party_username' => $p->client?->username,
                    'party_user_id' => $p->client_id,
                    'quest_id' => $p->quest_id,
                    'date' => $p->paid_at?->toDateString(),
                    'occurred_at' => $p->paid_at?->toIso8601String(),
                    'duration_label' => $p->tier ? ucfirst(str_replace('_', ' ', $p->tier)) : '—',
                    'amount_minor' => $gross,
                    'amount_display' => NgnMoney::format($gross),
                    'net_minor' => $net,
                    'net_display' => NgnMoney::format($net),
                    'status' => $p->status,
                    'status_label' => ucfirst($p->status),
                    'reference' => $p->paystack_reference,
                    'quest_title' => $p->quest?->title,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function platformFeeTransactionRows(Carbon $from, Carbon $to): Collection
    {
        return FinancialEscrowRecord::query()
            ->whereNotNull('fee_recognised_at')
            ->whereBetween('fee_recognised_at', [$from, $to])
            ->with(['client:id,name,username', 'freelancer:id,name,username', 'quest:id,title,reference_code'])
            ->orderByDesc('fee_recognised_at')
            ->limit(500)
            ->get()
            ->map(function (FinancialEscrowRecord $r) {
                $fee = (int) $r->platform_fee_minor;

                return [
                    'id' => $r->id,
                    'revenue_type' => 'platform_fee',
                    'revenue_type_label' => 'Platform fee',
                    'transaction_id' => 'FEE-'.$r->id,
                    'party_label' => trim(($r->client?->name ?? $r->client_name ?? '—').' → '.($r->freelancer?->name ?? $r->freelancer_name ?? '—')),
                    'party_user_id' => $r->client_id,
                    'date' => $r->fee_recognised_at?->toDateString(),
                    'occurred_at' => $r->fee_recognised_at?->toIso8601String(),
                    'duration_label' => 'Contract release',
                    'amount_minor' => $fee,
                    'amount_display' => NgnMoney::format($fee),
                    'net_minor' => $fee,
                    'net_display' => NgnMoney::format($fee),
                    'status' => 'earned',
                    'status_label' => 'Earned',
                    'reference' => $r->escrow_reference ?? $r->contract_reference,
                    'quest_title' => $r->quest?->title,
                    'contract_value_minor' => (int) $r->gross_minor,
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function premiumDetail(int $id): array
    {
        $payment = FreelancerSubscriptionPayment::query()->with(['user', 'subscription'])->findOrFail($id);
        $feePct = config('revenue_monitor.processor_fee_percent.premium', 5);
        $gross = (int) $payment->amount_minor;
        $fee = (int) round($gross * ($feePct / 100));

        return [
            'type' => 'premium',
            'subscriber' => $payment->user?->only(['id', 'name', 'username', 'verification_tier', 'email']),
            'plan' => $payment->billing_cycle === 'year' ? 'Premium Annual' : 'Premium Monthly',
            'charge_display' => NgnMoney::format($gross),
            'payment_method' => data_get($payment->meta, 'channel', 'Paystack'),
            'renewal_date' => $payment->subscription?->renewal_date?->toDateString(),
            'auto_renew' => (bool) ($payment->subscription?->auto_renew ?? true),
            'processor_fee_display' => NgnMoney::format($fee).' ('.$feePct.'%)',
            'net_display' => NgnMoney::format($gross - $fee),
            'status' => $payment->status,
            'transaction_id' => 'PRE-'.$payment->id,
            'reference' => $payment->paystack_reference,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boostDetail(int $id): array
    {
        $payment = QuestBoostPayment::query()->with(['client', 'quest', 'questBoost'])->findOrFail($id);
        $feePct = config('revenue_monitor.processor_fee_percent.quest_boost', 7);
        $gross = (int) $payment->amount_minor;
        $fee = (int) round($gross * ($feePct / 100));
        $boost = $payment->questBoost;
        $proposalCount = $boost ? Quest::query()->find($payment->quest_id)?->offers()->count() : 0;

        return [
            'type' => 'boost',
            'quest' => $payment->quest?->only(['id', 'title', 'reference_code']),
            'client' => $payment->client?->only(['id', 'name', 'username', 'verification_tier']),
            'tier' => $payment->tier,
            'charge_display' => NgnMoney::format($gross),
            'boost_period' => $boost ? $boost->starts_at?->toDateString().' – '.$boost->ends_at?->toDateString() : null,
            'proposals_received' => $proposalCount,
            'processor_fee_display' => NgnMoney::format($fee).' ('.$feePct.'%)',
            'net_display' => NgnMoney::format($gross - $fee),
            'status' => $payment->status,
            'boost_status' => $boost?->status,
            'transaction_id' => 'BST-'.$payment->id,
            'reference' => $payment->paystack_reference,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function platformFeeDetail(int $id): array
    {
        $record = FinancialEscrowRecord::query()->with(['client', 'freelancer', 'quest'])->findOrFail($id);
        $gross = (int) $record->gross_minor;
        $fee = (int) $record->platform_fee_minor;
        $pct = $gross > 0 ? round(($fee / $gross) * 100, 2) : 0;

        return [
            'type' => 'platform_fee',
            'quest' => $record->quest?->only(['id', 'title', 'reference_code']),
            'client' => $record->client?->only(['id', 'name', 'username']),
            'freelancer' => $record->freelancer?->only(['id', 'name', 'username']),
            'contract_value_display' => NgnMoney::format($gross),
            'platform_fee_display' => NgnMoney::format($fee),
            'fee_percent' => $pct,
            'status' => 'earned',
            'earned_at' => $record->fee_recognised_at?->toIso8601String(),
            'reference' => $record->escrow_reference ?? $record->contract_reference,
        ];
    }

    private function sumPremium(Carbon $from, Carbon $to): int
    {
        return (int) FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_minor');
    }

    private function sumBoost(Carbon $from, Carbon $to): int
    {
        return (int) QuestBoostPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_minor');
    }

    private function sumPlatformFees(Carbon $from, Carbon $to): int
    {
        return (int) LedgerEntry::query()
            ->where('ledger_account', 'platform_fee_revenue')
            ->where('side', 'credit')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('amount_minor');
    }

    private function sumPremiumRefunds(Carbon $from, Carbon $to): int
    {
        return (int) FreelancerSubscriptionPayment::query()
            ->whereIn('status', config('revenue_monitor.refund_statuses', []))
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_minor');
    }

    private function sumBoostRefunds(Carbon $from, Carbon $to): int
    {
        return (int) QuestBoostPayment::query()
            ->whereIn('status', config('revenue_monitor.refund_statuses', []))
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_minor');
    }

    private function processorFees(int $premium, int $boost): int
    {
        $pPct = config('revenue_monitor.processor_fee_percent.premium', 5);
        $bPct = config('revenue_monitor.processor_fee_percent.quest_boost', 7);

        return (int) round($premium * ($pPct / 100)) + (int) round($boost * ($bPct / 100));
    }

    /**
     * @return array<string, int>
     */
    private function dailyPremium(Carbon $from, Carbon $to): array
    {
        return FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as day, SUM(amount_minor) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function dailyBoost(Carbon $from, Carbon $to): array
    {
        return QuestBoostPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as day, SUM(amount_minor) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function dailyPlatform(Carbon $from, Carbon $to): array
    {
        return LedgerEntry::query()
            ->where('ledger_account', 'platform_fee_revenue')
            ->where('side', 'credit')
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('DATE(occurred_at) as day, SUM(amount_minor) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function growthPercent(int $current, int $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function exportPdf(Request $request)
    {
        $payload = $this->indexPayload($request);
        $request->merge(['page' => 1, 'per_page' => 500]);
        [$from, $to] = $this->resolveRange($request);
        $transactions = $this->transactions($request, $from, $to)['items'];
        $filename = 'revenue-monitor-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf';

        return Pdf::loadView('pdf.revenue-monitor-report', [
            'period' => $payload['period'],
            'overview' => $payload['overview'],
            'sidebar' => $payload['sidebar'],
            'trend_insights' => $payload['trend_insights'],
            'transactions' => $transactions,
            'generated_at' => now(),
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
