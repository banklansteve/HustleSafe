<?php

namespace App\Services\Matching;

use App\Enums\QuestStatus;
use App\Models\FreelancerMetric;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class FreelancerMetricsService
{
    public function __construct(
        protected VerificationEngineService $verificationEngine,
    ) {}

    public function forUser(User $freelancer, bool $forceRefresh = false): ?FreelancerMetric
    {
        $existing = FreelancerMetric::query()->where('user_id', $freelancer->id)->first();
        $maxAgeHours = (int) config('quest_matching.metrics_refresh_hours', 6);

        if (! $forceRefresh && $existing?->refreshed_at !== null && $existing->refreshed_at->gt(now()->subHours($maxAgeHours))) {
            return $existing;
        }

        return $this->refresh($freelancer);
    }

    public function refresh(User $freelancer): FreelancerMetric
    {
        $freelancer->loadMissing(['trustMetrics', 'role']);

        $completedBudgets = $this->medianOfLastCompletedBudgets($freelancer, 10);
        $nicheMap = $this->nicheCompletionsByCategory($freelancer);
        $quickCount = $this->quickTurnaroundCompletedCount($freelancer);

        $totalAsFreelancer = Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->count();
        $completedCount = Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->where('status', QuestStatus::Completed)
            ->count();
        $completionRate = $totalAsFreelancer > 0
            ? round(($completedCount / $totalAsFreelancer) * 100, 2)
            : 0.0;

        $disputeCount = QuestDispute::query()
            ->whereHas('quest', fn ($q) => $q->where('freelancer_id', $freelancer->id))
            ->where('created_at', '>=', now()->subMonths(6))
            ->count();

        $cancellationCount = Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->whereIn('status', [
                QuestStatus::CancelledMutual,
                QuestStatus::CancelledByAdmin,
                QuestStatus::WithdrawnByFreelancer,
            ])
            ->where('updated_at', '>=', now()->subMonths(6))
            ->count();

        $lastProposal = QuestOffer::query()
            ->where('freelancer_id', $freelancer->id)
            ->latest('created_at')
            ->value('created_at');

        return FreelancerMetric::query()->updateOrCreate(
            ['user_id' => $freelancer->id],
            [
                'location_state_id' => $freelancer->state_id,
                'location_lga_id' => $freelancer->local_government_id,
                'typical_job_value_minor' => $completedBudgets,
                'skills_list' => $this->extractSkillsList($freelancer),
                'completion_rate' => $completionRate,
                'average_rating' => $freelancer->trustMetrics?->avg_rating_as_freelancer,
                'verification_level' => $this->verificationEngine->effectiveLevel($freelancer),
                'last_proposal_at' => $lastProposal,
                'dispute_count_last_6_months' => $disputeCount,
                'cancellation_count_last_6_months' => $cancellationCount,
                'quick_turnaround_completed_count' => $quickCount,
                'niche_completions_by_category' => $nicheMap,
                'refreshed_at' => now(),
            ],
        );
    }

    public function refreshAll(int $chunk = 100): int
    {
        $count = 0;
        User::query()
            ->whereRelation('role', 'slug', 'freelancer')
            ->orderBy('id')
            ->chunkById($chunk, function (Collection $users) use (&$count): void {
                foreach ($users as $user) {
                    /** @var User $user */
                    $this->refresh($user);
                    $count++;
                }
            });

        return $count;
    }

    protected function medianOfLastCompletedBudgets(User $freelancer, int $limit): ?int
    {
        $values = Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->where('status', QuestStatus::Completed)
            ->whereNotNull('budget_amount_minor')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('budget_amount_minor')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = (int) floor(count($values) / 2);

        if (count($values) % 2 === 0) {
            return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
        }

        return $values[$middle];
    }

    /**
     * @return array<string, int>
     */
    protected function nicheCompletionsByCategory(User $freelancer): array
    {
        return Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->where('status', QuestStatus::Completed)
            ->whereNotNull('quest_category_id')
            ->selectRaw('quest_category_id, count(*) as total')
            ->groupBy('quest_category_id')
            ->pluck('total', 'quest_category_id')
            ->mapWithKeys(fn ($count, $categoryId) => [(string) $categoryId => (int) $count])
            ->all();
    }

    protected function quickTurnaroundCompletedCount(User $freelancer): int
    {
        $maxDays = (int) config('quest_matching.urgency_quick_delivery_max_days', 5);

        return Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->where('status', QuestStatus::Completed)
            ->where(function ($q) use ($maxDays): void {
                $q->where('estimated_completion_days', '<=', $maxDays)
                    ->orWhereRaw('TIMESTAMPDIFF(DAY, COALESCE(escrow_funded_at, created_at), completed_at) <= ?', [$maxDays]);
            })
            ->count();
    }

    /**
     * @return list<string>
     */
    protected function extractSkillsList(User $freelancer): array
    {
        $settings = $freelancer->public_profile_settings ?? [];
        $skills = Arr::wrap($settings['skills'] ?? []);

        if ($skills !== []) {
            return collect($skills)
                ->map(fn ($s) => strtolower(trim((string) $s)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return collect([$freelancer->headline, $freelancer->profession])
            ->filter()
            ->flatMap(fn ($s) => preg_split('/[,;|]+/', (string) $s) ?: [])
            ->map(fn ($s) => strtolower(trim($s)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
