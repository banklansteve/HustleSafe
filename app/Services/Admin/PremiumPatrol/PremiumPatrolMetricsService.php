<?php

namespace App\Services\Admin\PremiumPatrol;

use App\Enums\FreelancerSubscriptionStatus;
use App\Enums\FreelancerSubscriptionTier;
use App\Enums\QuestBoostStatus;
use App\Models\FreelancerSubscription;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\QuestBoost;
use App\Models\QuestBoostPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PremiumPatrolMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboardPayload(Carbon $from, Carbon $to): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $activePremium = FreelancerSubscription::query()
            ->where('tier', FreelancerSubscriptionTier::Pro->value)
            ->where('status', FreelancerSubscriptionStatus::Active->value)
            ->where(function ($q) use ($now): void {
                $q->whereNull('renewal_date')->orWhere('renewal_date', '>', $now);
            })
            ->count();

        $newPremium7d = FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $sevenDaysAgo)
            ->distinct('user_id')
            ->count('user_id');

        $churned30d = FreelancerSubscription::query()
            ->where(function ($q) use ($thirtyDaysAgo): void {
                $q->whereIn('status', [
                    FreelancerSubscriptionStatus::Cancelled->value,
                    FreelancerSubscriptionStatus::Expired->value,
                ])->where('cancelled_at', '>=', $thirtyDaysAgo);
            })
            ->orWhere(function ($q) use ($thirtyDaysAgo): void {
                $q->where('status', FreelancerSubscriptionStatus::AdminSuspended->value)
                    ->where('admin_suspended_at', '>=', $thirtyDaysAgo);
            })
            ->count();

        $premiumBase30d = max(1, FreelancerSubscription::query()
            ->where('tier', FreelancerSubscriptionTier::Pro->value)
            ->where('started_at', '<=', $thirtyDaysAgo)
            ->count());

        $mrrMinor = (int) FreelancerSubscription::query()
            ->where('tier', FreelancerSubscriptionTier::Pro->value)
            ->where('status', FreelancerSubscriptionStatus::Active->value)
            ->get()
            ->sum(fn (FreelancerSubscription $s) => $s->billing_cycle === 'year'
                ? (int) round($s->annual_price_minor / 12)
                : (int) $s->monthly_price_minor);

        $activeBoosts = QuestBoost::query()->activeNow()->count();
        $newBoosts7d = QuestBoost::query()->where('granted_at', '>=', $sevenDaysAgo)->count();

        $avgBoostDuration = QuestBoost::query()
            ->where('granted_at', '>=', $thirtyDaysAgo)
            ->get()
            ->avg(fn (QuestBoost $b) => $b->starts_at->diffInHours($b->ends_at) / 24);

        $boostRevenue30d = (int) QuestBoostPayment::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $thirtyDaysAgo)
            ->sum('amount_minor');

        return [
            'premium' => [
                'active_this_month' => $activePremium,
                'new_signups_7d' => $newPremium7d,
                'churn_rate_30d' => round(($churned30d / $premiumBase30d) * 100, 1),
                'mrr_minor' => $mrrMinor,
                'growth_chart' => $this->dailyPremiumGrowth($from, $to),
            ],
            'boosts' => [
                'active_live' => $activeBoosts,
                'new_7d' => $newBoosts7d,
                'avg_duration_days' => round((float) ($avgBoostDuration ?? 0), 1),
                'revenue_30d_minor' => $boostRevenue30d,
                'activity_chart' => $this->dailyBoostActivity($from, $to),
            ],
        ];
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    private function dailyPremiumGrowth(Carbon $from, Carbon $to): array
    {
        $rows = FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(paid_at) as day, COUNT(DISTINCT user_id) as cnt')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('cnt', 'day');

        return $this->fillDailySeries($from, $to, $rows);
    }

    /**
     * @return list<array{date: string, count: int, minor: int}>
     */
    private function dailyBoostActivity(Carbon $from, Carbon $to): array
    {
        $counts = QuestBoost::query()
            ->whereBetween('granted_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(granted_at) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('cnt', 'day');

        $revenue = QuestBoostPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(paid_at) as day, SUM(amount_minor) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $series = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date' => $key,
                'count' => (int) ($counts[$key] ?? 0),
                'minor' => (int) ($revenue[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @param  Collection<string, mixed>  $rows
     * @return list<array{date: string, count: int}>
     */
    private function fillDailySeries(Carbon $from, Carbon $to, Collection $rows): array
    {
        $series = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $series[] = ['date' => $key, 'count' => (int) ($rows[$key] ?? 0)];
            $cursor->addDay();
        }

        return $series;
    }
}
