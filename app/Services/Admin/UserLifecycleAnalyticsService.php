<?php

namespace App\Services\Admin;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserLifecycleAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'client_first_quest' => $this->clientFirstQuestMetrics(),
            'freelancer_first_contract' => $this->freelancerFirstContractMetrics(),
            'onboarding_funnel' => $this->onboardingFunnel(),
            'retention' => $this->retentionRates(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientFirstQuestMetrics(): array
    {
        $clients = User::query()
            ->where(function ($q): void {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'client'))
                    ->orWhere('account_type', 'client');
            })
            ->where('created_at', '>=', now()->subMonths(12));

        $total = (clone $clients)->count();
        $published = Quest::query()
            ->whereIn('client_id', (clone $clients)->pluck('id'))
            ->where('status', '!=', QuestStatus::Draft->value)
            ->select('client_id', DB::raw('MIN(created_at) as first_quest_at'))
            ->groupBy('client_id')
            ->get();

        $days = $published->map(function ($row) {
            $user = User::query()->find($row->client_id);
            if (! $user?->created_at || ! $row->first_quest_at) {
                return null;
            }

            return Carbon::parse($user->created_at)->diffInDays(Carbon::parse($row->first_quest_at));
        })->filter()->values();

        return [
            'median_days' => $this->median($days),
            'p75_days' => $this->percentile($days, 75),
            'conversion_rate_pct' => $total > 0 ? round(($published->count() / $total) * 100, 1) : 0,
            'cohorts' => $this->monthlyMedianSeries($days, (clone $clients)->get(['id', 'created_at']), 'first_quest'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function freelancerFirstContractMetrics(): array
    {
        $freelancers = User::query()
            ->whereHas('role', fn ($r) => $r->where('slug', 'freelancer'))
            ->where('created_at', '>=', now()->subMonths(12));

        $total = (clone $freelancers)->count();

        $firstWins = Quest::query()
            ->whereNotNull('accepted_quest_offer_id')
            ->whereNotNull('freelancer_id')
            ->whereIn('freelancer_id', (clone $freelancers)->pluck('id'))
            ->select('freelancer_id', DB::raw('MIN(COALESCE(escrow_funded_at, updated_at)) as first_win_at'))
            ->groupBy('freelancer_id')
            ->get();

        $days = $firstWins->map(function ($row) {
            $user = User::query()->find($row->freelancer_id);
            if (! $user?->created_at || ! $row->first_win_at) {
                return null;
            }

            return Carbon::parse($user->created_at)->diffInDays(Carbon::parse($row->first_win_at));
        })->filter()->values();

        return [
            'median_days' => $this->median($days),
            'p75_days' => $this->percentile($days, 75),
            'conversion_rate_pct' => $total > 0 ? round(($firstWins->count() / $total) * 100, 1) : 0,
            'cohorts' => $this->monthlyMedianSeries($days, (clone $freelancers)->get(['id', 'created_at']), 'first_contract'),
        ];
    }

    /**
     * @return list<array{step: string, count: int, drop_pct: float|null}>
     */
    private function onboardingFunnel(): array
    {
        $clientsRegistered = User::query()
            ->where(function ($q): void {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'client'))
                    ->orWhere('account_type', 'client');
            })
            ->where('created_at', '>=', now()->subDays(90))
            ->count();

        $clientsStartedDraft = User::query()
            ->where(function ($q): void {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'client'))
                    ->orWhere('account_type', 'client');
            })
            ->where('created_at', '>=', now()->subDays(90))
            ->whereHas('questsAsClient')
            ->count();

        $clientsPublished = User::query()
            ->where(function ($q): void {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'client'))
                    ->orWhere('account_type', 'client');
            })
            ->where('created_at', '>=', now()->subDays(90))
            ->whereHas('questsAsClient', fn ($q) => $q->where('status', '!=', QuestStatus::Draft->value))
            ->count();

        $freelancersRegistered = User::query()
            ->whereHas('role', fn ($r) => $r->where('slug', 'freelancer'))
            ->where('created_at', '>=', now()->subDays(90))
            ->count();

        $freelancersViewed = User::query()
            ->whereHas('role', fn ($r) => $r->where('slug', 'freelancer'))
            ->where('created_at', '>=', now()->subDays(90))
            ->whereHas('questOffers')
            ->count();

        $freelancersSubmitted = User::query()
            ->whereHas('role', fn ($r) => $r->where('slug', 'freelancer'))
            ->where('created_at', '>=', now()->subDays(90))
            ->whereHas('questOffers', fn ($q) => $q->whereIn('status', ['submitted', 'shortlisted', 'pending_award', 'accepted']))
            ->count();

        $freelancersWon = User::query()
            ->whereHas('role', fn ($r) => $r->where('slug', 'freelancer'))
            ->where('created_at', '>=', now()->subDays(90))
            ->whereHas('questOffers', fn ($q) => $q->where('status', 'accepted'))
            ->count();

        return [
            $this->funnelStep('Clients registered', $clientsRegistered, null),
            $this->funnelStep('Started Quest draft', $clientsStartedDraft, $clientsRegistered),
            $this->funnelStep('Published first Quest', $clientsPublished, $clientsStartedDraft),
            $this->funnelStep('Freelancers registered', $freelancersRegistered, null),
            $this->funnelStep('Submitted a proposal', $freelancersSubmitted, $freelancersRegistered),
            $this->funnelStep('Won first contract', $freelancersWon, $freelancersSubmitted),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function retentionRates(): array
    {
        return [
            'clients' => $this->retentionForRole('client', [30, 60, 90]),
            'freelancers' => $this->retentionForRole('freelancer', [30, 60, 90]),
        ];
    }

    /**
     * @param  list<int>  $windows
     * @return list<array{days: int, rate_pct: float, cohort_size: int}>
     */
    private function retentionForRole(string $roleSlug, array $windows): array
    {
        $cohortStart = now()->subDays(max($windows) + 30);

        $query = User::query();
        if ($roleSlug === 'client') {
            $query->where(function ($q): void {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'client'))
                    ->orWhere('account_type', 'client');
            });
        } else {
            $query->whereHas('role', fn ($r) => $r->where('slug', $roleSlug));
        }

        $cohort = (clone $query)
            ->whereBetween('created_at', [$cohortStart, now()->subDays(max($windows))])
            ->get(['id', 'created_at', 'last_active_at']);

        $size = $cohort->count();

        return collect($windows)->map(function (int $days) use ($cohort, $size): array {
            if ($size === 0) {
                return ['days' => $days, 'rate_pct' => 0.0, 'cohort_size' => 0];
            }

            $retained = $cohort->filter(function (User $user) use ($days): bool {
                if ($user->last_active_at === null) {
                    return false;
                }

                $target = $user->created_at?->copy()->addDays($days);
                if ($target === null || $target->isFuture()) {
                    return false;
                }

                return $user->last_active_at->greaterThanOrEqualTo($target);
            })->count();

            return [
                'days' => $days,
                'rate_pct' => round(($retained / $size) * 100, 1),
                'cohort_size' => $size,
            ];
        })->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|float>  $values
     */
    private function median($values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();
        $mid = (int) floor($sorted->count() / 2);

        return $sorted->count() % 2 === 0
            ? round(($sorted[$mid - 1] + $sorted[$mid]) / 2, 1)
            : round((float) $sorted[$mid], 1);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|float>  $values
     */
    private function percentile($values, int $pct): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();
        $index = (int) ceil(($pct / 100) * $sorted->count()) - 1;

        return round((float) $sorted[max(0, $index)], 1);
    }

    /**
     * @return array{step: string, count: int, drop_pct: float|null}
     */
    private function funnelStep(string $step, int $count, ?int $previous): array
    {
        $drop = ($previous !== null && $previous > 0)
            ? round((1 - ($count / $previous)) * 100, 1)
            : null;

        return ['step' => $step, 'count' => $count, 'drop_pct' => $drop];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|float>  $days
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @return list<array{month: string, median_days: float|null, sample: int}>
     */
    private function monthlyMedianSeries($days, $users, string $kind): array
    {
        return collect(range(5, 0))->map(function (int $offset) use ($users, $kind): array {
            $start = now()->startOfMonth()->subMonths($offset);
            $end = $start->copy()->endOfMonth();
            $cohortIds = $users->filter(fn (User $u) => $u->created_at && $u->created_at->between($start, $end))->pluck('id');

            if ($cohortIds->isEmpty()) {
                return ['month' => $start->format('M Y'), 'median_days' => null, 'sample' => 0];
            }

            if ($kind === 'first_quest') {
                $samples = Quest::query()
                    ->whereIn('client_id', $cohortIds)
                    ->where('status', '!=', QuestStatus::Draft->value)
                    ->select('client_id', DB::raw('MIN(created_at) as first_at'))
                    ->groupBy('client_id')
                    ->get()
                    ->map(function ($row) use ($users) {
                        $user = $users->firstWhere('id', $row->client_id);
                        if (! $user?->created_at || ! $row->first_at) {
                            return null;
                        }

                        return Carbon::parse($user->created_at)->diffInDays(Carbon::parse($row->first_at));
                    })
                    ->filter();
            } else {
                $samples = Quest::query()
                    ->whereIn('freelancer_id', $cohortIds)
                    ->whereNotNull('accepted_quest_offer_id')
                    ->select('freelancer_id', DB::raw('MIN(COALESCE(escrow_funded_at, updated_at)) as first_at'))
                    ->groupBy('freelancer_id')
                    ->get()
                    ->map(function ($row) use ($users) {
                        $user = $users->firstWhere('id', $row->freelancer_id);
                        if (! $user?->created_at || ! $row->first_at) {
                            return null;
                        }

                        return Carbon::parse($user->created_at)->diffInDays(Carbon::parse($row->first_at));
                    })
                    ->filter();
            }

            return [
                'month' => $start->format('M Y'),
                'median_days' => $this->median($samples),
                'sample' => $samples->count(),
            ];
        })->all();
    }
}
