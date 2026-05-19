<?php

namespace App\Services\Admin;

use App\Enums\QuestStatus;
use App\Models\AdminFinancialLedgerEntry;
use App\Models\FeaturedQuestListing;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\State;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminInsightsService
{
    public function payload(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'vitals' => $this->vitals(),
            'health_score' => $this->platformHealthScore(),
            'gmv_trend' => $this->gmvTrend(),
            'revenue_breakdown' => $this->revenueBreakdown(),
            'quest_funnel' => $this->questFunnel(),
            'user_registrations' => $this->userRegistrationsByType(),
            'verification_distribution' => $this->verificationDistribution(),
            'category_heatmap' => $this->categoryPerformanceHeatmap(),
            'disputes' => $this->disputeInsights(),
            'leaderboards' => [
                'freelancers' => $this->topEarningFreelancers(),
                'clients' => $this->topSpendingClients(),
            ],
            'geo' => $this->geographicActivity(),
            'escrow_flow' => $this->escrowFlow(),
            'proposal_heatmap' => $this->proposalActivityHeatmap(),
            'payouts' => $this->payoutSuccessRate(),
            'retention' => $this->retentionCohorts(),
        ];
    }

    private function vitals(): array
    {
        $month = now()->startOfMonth();
        $escrowBalance = (int) Quest::query()
            ->whereNotNull('escrow_funded_at')
            ->sum(DB::raw('coalesce(budget_amount_minor, 0) - coalesce(paid_out_minor, 0) - coalesce(refunded_minor, 0)'));

        return [
            ['label' => 'Active Quests', 'value' => number_format(Quest::query()->where('status', QuestStatus::Open)->count()), 'tone' => 'primary'],
            ['label' => 'Active Contracts', 'value' => number_format(Quest::query()->whereIn('status', [QuestStatus::Assigned, QuestStatus::InProgress])->count()), 'tone' => 'blue'],
            ['label' => 'Escrow Balance', 'value' => $this->money($escrowBalance), 'tone' => 'emerald'],
            ['label' => 'Revenue This Month', 'value' => $this->money($this->revenueMinor($month, now())), 'tone' => 'amber'],
        ];
    }

    private function gmvTrend(): array
    {
        $months = $this->months(11);

        return [
            'series' => $months->map(fn (CarbonImmutable $month) => [
                'label' => $month->format('M'),
                'month' => $month->format('Y-m'),
                'value_minor' => $this->gmvMinor($month, $month->endOfMonth()),
                'previous_year_minor' => $this->gmvMinor($month->subYear(), $month->subYear()->endOfMonth()),
            ])->values(),
        ];
    }

    private function revenueBreakdown(): array
    {
        $months = $this->months(5);
        $streams = [
            'service_fees' => 'Service fees',
            'featured_listings' => 'Boost packages',
            'dispute_fees' => 'Dispute fees',
            'other' => 'Other revenue',
        ];

        return [
            'streams' => $streams,
            'months' => $months->map(function (CarbonImmutable $month) {
                $start = $month->startOfMonth();
                $end = $month->endOfMonth();
                $service = $this->ledgerSum(['platform_fee', 'service_fee'], $start, $end);
                $featured = (int) FeaturedQuestListing::query()
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('amount_paid_minor');
                $dispute = $this->ledgerSum(['dispute_fee', 'dispute_resolution_fee'], $start, $end);
                $totalLedger = $this->revenueMinor($start, $end);
                $other = max(0, $totalLedger - $service - $featured - $dispute);

                return [
                    'label' => $month->format('M'),
                    'service_fees' => $service,
                    'featured_listings' => $featured,
                    'dispute_fees' => $dispute,
                    'other' => $other,
                ];
            })->values(),
        ];
    }

    private function questFunnel(): array
    {
        $posted = Quest::query()->count();
        $withProposal = Quest::query()->whereHas('offers')->count();
        $shortlisted = Quest::query()->whereHas('offers', fn (Builder $q) => $q->whereNotNull('shortlisted_at'))->count();
        $contracted = Quest::query()->whereNotNull('accepted_quest_offer_id')->orWhereNotNull('freelancer_id')->count();
        $completed = Quest::query()->where('status', QuestStatus::Completed)->count();
        $steps = [
            ['key' => 'posted', 'label' => 'Quests posted', 'count' => $posted],
            ['key' => 'proposal', 'label' => 'Received proposal', 'count' => $withProposal],
            ['key' => 'shortlisted', 'label' => 'Freelancer shortlisted', 'count' => $shortlisted],
            ['key' => 'contracted', 'label' => 'Contract funded / hired', 'count' => $contracted],
            ['key' => 'completed', 'label' => 'Completed contracts', 'count' => $completed],
        ];

        $previous = null;
        return collect($steps)->map(function (array $step) use (&$previous) {
            $rate = $previous === null ? 100 : ($previous > 0 ? round(($step['count'] / $previous) * 100, 1) : 0);
            $loss = $previous === null ? 0 : max(0, round(100 - $rate, 1));
            $previous = $step['count'];

            return [...$step, 'conversion_rate' => $rate, 'dropoff_rate' => $loss];
        })->values()->all();
    }

    private function userRegistrationsByType(): array
    {
        $weeks = collect(range(7, 0))->map(fn (int $i) => CarbonImmutable::now()->startOfWeek()->subWeeks($i));

        return $weeks->map(function (CarbonImmutable $week) {
            $rows = User::query()
                ->selectRaw("coalesce(account_type, 'unknown') as account_type, count(*) as aggregate")
                ->whereBetween('created_at', [$week, $week->endOfWeek()])
                ->groupBy('account_type')
                ->pluck('aggregate', 'account_type');

            return [
                'label' => $week->format('M j'),
                'clients' => (int) ($rows['client'] ?? $rows['business'] ?? 0),
                'freelancers' => (int) ($rows['freelancer'] ?? 0),
            ];
        })->values()->all();
    }

    private function verificationDistribution(): array
    {
        $rows = User::query()
            ->selectRaw('coalesce(verification_tier, kyc_tier, 0) as tier, count(*) as aggregate')
            ->whereNull('banned_at')
            ->groupBy('tier')
            ->pluck('aggregate', 'tier');
        $total = max(1, (int) $rows->sum());
        $capabilities = [
            0 => 'Email-only account, limited trust signals.',
            1 => 'Phone-confirmed user with basic platform access.',
            2 => 'NIN-level identity confidence and higher limits.',
            3 => 'Fully verified individual account.',
            4 => 'Business verified, strongest commercial trust.',
        ];

        return [
            'total' => $total,
            'tiers' => collect(range(0, 4))->map(fn (int $tier) => [
                'tier' => $tier,
                'label' => 'Tier '.$tier,
                'count' => (int) ($rows[$tier] ?? 0),
                'percent' => round(((int) ($rows[$tier] ?? 0) / $total) * 100, 1),
                'capability' => $capabilities[$tier],
            ])->values(),
        ];
    }

    private function categoryPerformanceHeatmap(): array
    {
        $parents = QuestCategory::query()->parents()->orderBy('sort_order')->orderBy('name')->limit(12)->get(['id', 'name']);
        $start = now()->startOfMonth();
        $averages = [
            'posted' => max(1, Quest::query()->where('created_at', '>=', $start)->count() / max(1, $parents->count())),
            'fill_rate' => $this->pct(Quest::query()->whereNotNull('accepted_quest_offer_id')->count(), Quest::query()->count()),
            'avg_budget' => (float) Quest::query()->avg('budget_amount_minor'),
            'avg_proposals' => (float) Quest::query()->avg('offers_count'),
            'dispute_rate' => $this->pct(QuestDispute::query()->count(), max(1, Quest::query()->whereNotNull('accepted_quest_offer_id')->count())),
        ];

        return [
            'metrics' => [
                ['key' => 'posted', 'label' => 'Posted this month'],
                ['key' => 'fill_rate', 'label' => 'Fill rate'],
                ['key' => 'avg_budget', 'label' => 'Avg budget'],
                ['key' => 'avg_proposals', 'label' => 'Avg proposals'],
                ['key' => 'dispute_rate', 'label' => 'Dispute rate'],
            ],
            'rows' => $parents->map(function (QuestCategory $parent) use ($start, $averages) {
                $categoryIds = QuestCategory::query()->where('parent_id', $parent->id)->pluck('id')->push($parent->id);
                $posted = Quest::query()->whereIn('quest_category_id', $categoryIds)->where('created_at', '>=', $start)->count();
                $total = Quest::query()->whereIn('quest_category_id', $categoryIds)->count();
                $filled = Quest::query()->whereIn('quest_category_id', $categoryIds)->whereNotNull('accepted_quest_offer_id')->count();
                $disputes = QuestDispute::query()->whereHas('quest', fn (Builder $q) => $q->whereIn('quest_category_id', $categoryIds))->count();
                $values = [
                    'posted' => $posted,
                    'fill_rate' => $this->pct($filled, $total),
                    'avg_budget' => (int) Quest::query()->whereIn('quest_category_id', $categoryIds)->avg('budget_amount_minor'),
                    'avg_proposals' => round((float) Quest::query()->whereIn('quest_category_id', $categoryIds)->avg('offers_count'), 1),
                    'dispute_rate' => $this->pct($disputes, max(1, $filled)),
                ];

                return [
                    'category' => $parent->name,
                    'values' => collect($values)->map(fn ($value, $key) => [
                        'value' => $value,
                        'label' => in_array($key, ['fill_rate', 'dispute_rate'], true) ? $value.'%' : ($key === 'avg_budget' ? $this->money((int) $value) : (string) $value),
                        'tone' => $this->heatTone((float) $value, (float) ($averages[$key] ?: 1), $key === 'dispute_rate'),
                    ])->all(),
                ];
            })->values(),
        ];
    }

    private function disputeInsights(): array
    {
        return [
            'months' => $this->months(11)->map(function (CarbonImmutable $month) {
                $contracts = Quest::query()->whereBetween('escrow_funded_at', [$month, $month->endOfMonth()])->count();
                $disputes = QuestDispute::query()->whereBetween('created_at', [$month, $month->endOfMonth()])->count();
                $outcomes = QuestDispute::query()
                    ->selectRaw("coalesce(resolution_outcome, 'unresolved') as outcome, count(*) as aggregate")
                    ->whereBetween('resolved_at', [$month, $month->endOfMonth()])
                    ->groupBy('outcome')
                    ->pluck('aggregate', 'outcome');

                return [
                    'label' => $month->format('M'),
                    'rate' => $this->pct($disputes, $contracts),
                    'outcomes' => [
                        'client_refund' => (int) ($outcomes['client_refund'] ?? $outcomes['full_refund_to_client'] ?? 0),
                        'freelancer_release' => (int) ($outcomes['freelancer_release'] ?? $outcomes['full_release_to_freelancer'] ?? 0),
                        'split' => (int) ($outcomes['split'] ?? $outcomes['split_settlement'] ?? 0),
                        'dismissed' => (int) ($outcomes['dismissed'] ?? 0),
                        'unresolved' => (int) ($outcomes['unresolved'] ?? 0),
                    ],
                ];
            })->values(),
        ];
    }

    private function topEarningFreelancers(): array
    {
        $start = now()->startOfMonth();
        return User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.avatar_url')
            ->selectRaw('coalesce(sum(quests.paid_out_minor), 0) as earnings_minor')
            ->selectRaw('count(distinct quest_disputes.id) as disputes_count')
            ->leftJoin('quests', 'quests.freelancer_id', '=', 'users.id')
            ->leftJoin('quest_disputes', 'quest_disputes.quest_id', '=', 'quests.id')
            ->where('quests.completed_at', '>=', $start)
            ->groupBy('users.id', 'users.name', 'users.email', 'users.avatar_url')
            ->orderByDesc('earnings_minor')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'avatar_url' => $row->avatar_url,
                'value_minor' => (int) $row->earnings_minor,
                'value' => $this->money((int) $row->earnings_minor),
                'trust_score' => (int) (User::query()->find($row->id)?->trust_score ?? 0),
                'disputes_count' => (int) $row->disputes_count,
                'completion_rate' => $this->pct(Quest::query()->where('freelancer_id', $row->id)->where('status', QuestStatus::Completed)->count(), Quest::query()->where('freelancer_id', $row->id)->count()),
            ])->values()->all();
    }

    private function topSpendingClients(): array
    {
        $start = now()->startOfMonth();
        return User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.avatar_url')
            ->selectRaw('coalesce(sum(quests.budget_amount_minor), 0) as spend_minor')
            ->selectRaw("sum(case when quests.status in ('assigned', 'in_progress') then 1 else 0 end) as active_contracts")
            ->selectRaw('count(distinct quest_disputes.id) as disputes_count')
            ->leftJoin('quests', 'quests.client_id', '=', 'users.id')
            ->leftJoin('quest_disputes', 'quest_disputes.quest_id', '=', 'quests.id')
            ->where('quests.escrow_funded_at', '>=', $start)
            ->groupBy('users.id', 'users.name', 'users.email', 'users.avatar_url')
            ->orderByDesc('spend_minor')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'avatar_url' => $row->avatar_url,
                'value_minor' => (int) $row->spend_minor,
                'value' => $this->money((int) $row->spend_minor),
                'active_contracts' => (int) $row->active_contracts,
                'disputes_count' => (int) $row->disputes_count,
            ])->values()->all();
    }

    private function geographicActivity(): array
    {
        $states = State::query()->orderBy('name')->get(['id', 'name', 'code']);
        $users = User::query()->selectRaw('state_id, count(*) as aggregate')->whereNotNull('state_id')->groupBy('state_id')->pluck('aggregate', 'state_id');
        $quests = Quest::query()->selectRaw('state_id, count(*) as aggregate')->whereNotNull('state_id')->groupBy('state_id')->pluck('aggregate', 'state_id');
        $value = Quest::query()->selectRaw('state_id, coalesce(sum(budget_amount_minor), 0) as aggregate')->whereNotNull('state_id')->whereNotNull('escrow_funded_at')->groupBy('state_id')->pluck('aggregate', 'state_id');

        return [
            'states' => $states->map(fn (State $state) => [
                'id' => $state->id,
                'name' => $state->name,
                'code' => $state->code,
                'users' => (int) ($users[$state->id] ?? 0),
                'quests' => (int) ($quests[$state->id] ?? 0),
                'contract_value_minor' => (int) ($value[$state->id] ?? 0),
                'contract_value' => $this->money((int) ($value[$state->id] ?? 0)),
            ])->values(),
        ];
    }

    private function escrowFlow(): array
    {
        $start = now()->startOfMonth();
        $funded = (int) Quest::query()->where('escrow_funded_at', '>=', $start)->sum('budget_amount_minor');
        $released = (int) Quest::query()->where('completed_at', '>=', $start)->sum('paid_out_minor');
        $refunded = (int) Quest::query()->where('updated_at', '>=', $start)->sum('refunded_minor');
        $held = max(0, $funded - $released - $refunded);
        $frozen = (int) Quest::query()->whereNotNull('escrow_frozen_at')->sum(DB::raw('coalesce(budget_amount_minor, 0) - coalesce(paid_out_minor, 0) - coalesce(refunded_minor, 0)'));

        return [
            'inflow_minor' => $funded,
            'nodes' => [
                ['key' => 'funded', 'label' => 'Client payments', 'value' => $this->money($funded), 'minor' => $funded],
                ['key' => 'released', 'label' => 'Released to freelancers', 'value' => $this->money($released), 'minor' => $released],
                ['key' => 'refunded', 'label' => 'Refunded to clients', 'value' => $this->money($refunded), 'minor' => $refunded],
                ['key' => 'held', 'label' => 'Held / frozen', 'value' => $this->money($held + $frozen), 'minor' => $held + $frozen],
            ],
        ];
    }

    private function proposalActivityHeatmap(): array
    {
        $rows = QuestOffer::query()
            ->selectRaw('dayofweek(created_at) as dow, hour(created_at) as hour, count(*) as aggregate')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('dow', 'hour')
            ->get();
        $lookup = $rows->mapWithKeys(fn ($row) => [((int) $row->dow).'-'.((int) $row->hour) => (int) $row->aggregate]);
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        return [
            'days' => $days,
            'hours' => range(0, 23),
            'max' => max(1, (int) $lookup->max()),
            'cells' => collect(range(0, 6))->flatMap(function (int $dayIndex) use ($lookup) {
                $mysqlDow = $dayIndex === 6 ? 1 : $dayIndex + 2;
                return collect(range(0, 23))->map(fn (int $hour) => [
                    'day' => $dayIndex,
                    'hour' => $hour,
                    'count' => (int) ($lookup[$mysqlDow.'-'.$hour] ?? 0),
                ]);
            })->values(),
        ];
    }

    private function platformHealthScore(): array
    {
        $posted = max(1, Quest::query()->count());
        $contracted = Quest::query()->whereNotNull('accepted_quest_offer_id')->count();
        $completed = Quest::query()->where('status', QuestStatus::Completed)->count();
        $disputes = QuestDispute::query()->count();
        $verified = User::query()->whereRaw('coalesce(verification_tier, kyc_tier, 0) >= 2')->count();
        $users = max(1, User::query()->count());
        $retained = $this->latestRetentionPct();
        $growth = $this->pct(User::query()->where('created_at', '>=', now()->subDays(30))->count(), max(1, User::query()->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count()));
        $components = [
            ['metric' => 'Quest fill rate', 'value' => $this->pct($contracted, $posted), 'weight' => 25],
            ['metric' => 'Completion rate', 'value' => $this->pct($completed, max(1, $contracted)), 'weight' => 20],
            ['metric' => 'Low dispute pressure', 'value' => max(0, 100 - $this->pct($disputes, max(1, $contracted)) * 4), 'weight' => 15],
            ['metric' => 'Verification completion', 'value' => $this->pct($verified, $users), 'weight' => 15],
            ['metric' => 'User retention', 'value' => $retained, 'weight' => 15],
            ['metric' => 'Active user growth', 'value' => min(100, $growth), 'weight' => 10],
        ];
        $score = round(collect($components)->sum(fn ($row) => ($row['value'] * $row['weight']) / 100), 1);

        return [
            'score' => $score,
            'label' => $score >= 70 ? 'Healthy' : ($score >= 40 ? 'Fair' : 'Poor'),
            'components' => collect($components)->map(fn ($row) => [...$row, 'value_label' => round($row['value'], 1).'%', 'trend' => $row['value'] >= 50 ? 'up' : 'down'])->values(),
        ];
    }

    private function payoutSuccessRate(): array
    {
        $payouts = AdminFinancialLedgerEntry::query()->where('type', 'payout');
        $counts = (clone $payouts)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $total = max(1, (int) $counts->sum());

        return [
            'split' => [
                ['label' => 'Successful', 'status' => 'posted', 'count' => (int) ($counts['posted'] ?? $counts['successful'] ?? 0), 'percent' => round(((int) ($counts['posted'] ?? $counts['successful'] ?? 0) / $total) * 100, 1)],
                ['label' => 'Failed', 'status' => 'failed', 'count' => (int) ($counts['failed'] ?? 0), 'percent' => round(((int) ($counts['failed'] ?? 0) / $total) * 100, 1)],
                ['label' => 'Pending retry', 'status' => 'pending', 'count' => (int) ($counts['pending'] ?? 0), 'percent' => round(((int) ($counts['pending'] ?? 0) / $total) * 100, 1)],
            ],
            'failure_reasons' => (clone $payouts)
                ->selectRaw("coalesce(json_unquote(json_extract(meta, '$.failure_reason')), admin_reason, 'Unspecified failure') as reason, count(*) as aggregate")
                ->where('status', 'failed')
                ->groupBy('reason')
                ->orderByDesc('aggregate')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ['reason' => $row->reason, 'count' => (int) $row->aggregate])
                ->values(),
        ];
    }

    private function retentionCohorts(): array
    {
        $cohorts = collect(range(6, 0))->map(fn (int $i) => CarbonImmutable::now()->startOfMonth()->subMonths($i));

        return [
            'columns' => collect(range(0, 6))->map(fn (int $i) => 'Month '.$i),
            'rows' => $cohorts->map(function (CarbonImmutable $cohortStart) {
                $cohortEnd = $cohortStart->endOfMonth();
                $ids = User::query()->whereBetween('created_at', [$cohortStart, $cohortEnd])->pluck('id');
                $size = max(1, $ids->count());

                return [
                    'label' => $cohortStart->format('M Y'),
                    'size' => $ids->count(),
                    'cells' => collect(range(0, 6))->map(function (int $offset) use ($ids, $size, $cohortStart) {
                        $periodEnd = $cohortStart->addMonths($offset)->endOfMonth();
                        if ($periodEnd->isFuture()) {
                            return null;
                        }
                        $active = User::query()->whereIn('id', $ids)->where('last_active_at', '>=', $periodEnd->subDays(30))->count();

                        return ['percent' => round(($active / $size) * 100, 1)];
                    })->values(),
                ];
            })->values(),
        ];
    }

    private function latestRetentionPct(): float
    {
        $cohort = CarbonImmutable::now()->startOfMonth()->subMonths(2);
        $ids = User::query()->whereBetween('created_at', [$cohort, $cohort->endOfMonth()])->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        return $this->pct(User::query()->whereIn('id', $ids)->where('last_active_at', '>=', $cohort->addMonth()->endOfMonth())->count(), $ids->count());
    }

    private function months(int $back): Collection
    {
        return collect(range($back, 0))->map(fn (int $i) => CarbonImmutable::now()->startOfMonth()->subMonths($i));
    }

    private function gmvMinor(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) Quest::query()->whereBetween('escrow_funded_at', [$start, $end])->sum('budget_amount_minor');
    }

    private function revenueMinor($start, $end): int
    {
        return (int) AdminFinancialLedgerEntry::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->whereIn('type', ['platform_fee', 'service_fee', 'featured_listing_payment', 'dispute_fee', 'admin_adjustment'])
            ->sum('fee_amount_minor');
    }

    private function ledgerSum(array $types, $start, $end): int
    {
        return (int) AdminFinancialLedgerEntry::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->whereIn('type', $types)
            ->sum(DB::raw('greatest(fee_amount_minor, gross_amount_minor)'));
    }

    private function pct(int|float $part, int|float $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
    }

    private function heatTone(float $value, float $average, bool $inverse = false): string
    {
        $ratio = $average > 0 ? $value / $average : 0;
        if ($inverse) {
            return $ratio <= 0.8 ? 'good' : ($ratio <= 1.2 ? 'warn' : 'bad');
        }

        return $ratio >= 1.2 ? 'good' : ($ratio >= 0.8 ? 'warn' : 'bad');
    }

    private function money(int $minor): string
    {
        return '₦'.number_format($minor / 100, 2);
    }
}
