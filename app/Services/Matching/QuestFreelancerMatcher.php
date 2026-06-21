<?php

namespace App\Services\Matching;

use App\Models\FreelancerMetric;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Freelancer\FreelancerProSubscriptionService;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Support\Collection;

class QuestFreelancerMatcher
{
    public function __construct(
        protected QuestMatchScoreCalculator $calculator,
        protected FreelancerMetricsService $metricsService,
        protected VerificationEngineService $verificationEngine,
        protected FreelancerProSubscriptionService $proMembership,
        protected QuestRemoteMatchPolicy $remoteMatchPolicy,
    ) {}

    /**
     * @return array{
     *   recommendations: list<array<string, mixed>>,
     *   stats: array{lga: int, state: int, national: int, total: int, label: string},
     * }
     */
    public function recommendationsForQuest(Quest $quest, int $limit = 10): array
    {
        $categoryId = (int) ($quest->quest_category_id ?? 0);
        if ($categoryId <= 0) {
            return [
                'recommendations' => [],
                'stats' => ['lga' => 0, 'state' => 0, 'national' => 0, 'total' => 0, 'label' => ''],
            ];
        }

        $quest->loadMissing(['stateModel:id,name', 'localGovernment:id,name']);

        $proposedFreelancerIds = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->pluck('freelancer_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $candidates = User::query()
            ->whereRelation('role', 'slug', 'freelancer')
            ->where('users.id', '<>', $quest->client_id)
            ->when($proposedFreelancerIds !== [], fn ($q) => $q->whereNotIn('users.id', $proposedFreelancerIds))
            ->whereHas('questCategoryPreferences', fn ($q) => $q->where('quest_categories.id', $categoryId))
            ->with(['trustMetrics', 'stateModel:id,name', 'localGovernmentModel:id,name'])
            ->limit(200)
            ->get();

        $metricsByUser = FreelancerMetric::query()
            ->whereIn('user_id', $candidates->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $lgaCount = 0;
        $stateCount = 0;
        $nationalCount = 0;
        $remoteQuest = $this->remoteMatchPolicy->isLocationAgnostic($quest);

        $scored = $candidates->map(function (User $freelancer) use ($quest, $metricsByUser, &$lgaCount, &$stateCount, &$nationalCount, $remoteQuest) {
            if (! $this->passesHardGates($freelancer, $quest)) {
                return null;
            }

            $metrics = $metricsByUser->get($freelancer->id)
                ?? $this->metricsService->forUser($freelancer);

            $breakdown = $this->calculator->score($freelancer, $quest, $metrics);
            if (! $breakdown['passes_skills_gate'] || ! $breakdown['passes_language_gate']) {
                return null;
            }

            $tier = $breakdown['location_tier'];
            if ($remoteQuest || $tier === 'remote') {
                $nationalCount++;
            } elseif ($tier === 'same_lga') {
                $lgaCount++;
            } elseif ($tier === 'same_state') {
                $stateCount++;
            } else {
                $nationalCount++;
            }

            return [
                'freelancer' => $freelancer,
                'match_score' => (int) round($breakdown['total']),
                'match_quality' => $breakdown['quality'],
                'match_breakdown' => $breakdown['breakdown_lines'],
                'why_recommended' => $this->whyRecommendedLine($freelancer, $quest, $breakdown),
                'location_tier' => $tier,
                'is_pro' => $this->proMembership->isPro($freelancer),
            ];
        })->filter()->sortByDesc(fn (array $row) => [
            $row['match_score'],
            ($row['is_pro'] ?? false) ? 1 : 0,
        ])->values();

        $total = $scored->count();
        $label = $this->statsLabel($quest, $lgaCount, $stateCount, $nationalCount, $remoteQuest);

        $recommendations = $scored->take($limit)->map(function (array $row): array {
            /** @var User $u */
            $u = $row['freelancer'];

            return [
                'id' => $u->id,
                'name' => $u->name,
                'first_name' => $u->first_name,
                'slug' => $u->slug,
                'avatar_url' => $u->avatar_url,
                'verification_level' => $this->verificationEngine->effectiveLevel($u),
                'trust' => (int) ($u->trustMetrics?->freelancer_trust_score ?? 0),
                'rating' => $u->trustMetrics?->avg_rating_as_freelancer,
                'location' => trim(implode(', ', array_filter([
                    $u->localGovernmentModel?->name,
                    $u->stateModel?->name,
                ]))),
                'match_score' => $row['match_score'],
                'match_quality' => $row['match_quality'],
                'match_breakdown' => $row['match_breakdown'],
                'why_recommended' => $row['why_recommended'],
                'is_pro' => (bool) ($row['is_pro'] ?? false),
                'profile_url' => $u->slug ? route('freelancers.public', $u->slug) : null,
            ];
        })->all();

        return [
            'recommendations' => $recommendations,
            'stats' => [
                'lga' => $lgaCount,
                'state' => $stateCount,
                'national' => $nationalCount,
                'total' => $total,
                'label' => $label,
            ],
        ];
    }

    protected function passesHardGates(User $freelancer, Quest $quest): bool
    {
        if ($this->activeJobCount($freelancer) >= $this->activeJobLimit($freelancer)) {
            return false;
        }

        $budget = (int) ($quest->budget_amount_minor ?? 0);
        if ($budget > 0 && ! $this->verificationEngine->freelancerCanProposeForBudget($freelancer, $budget)) {
            return false;
        }

        return true;
    }

    protected function activeJobCount(User $freelancer): int
    {
        return Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->whereIn('status', config('quest_matching.active_job_statuses', []))
            ->count();
    }

    protected function activeJobLimit(User $freelancer): int
    {
        $level = $this->verificationEngine->effectiveLevel($freelancer);
        $map = config('quest_matching.active_job_limits_by_level', []);

        return (int) ($map[$level] ?? $map[(string) $level] ?? 2);
    }

    /**
     * @param  array<string, mixed>  $breakdown
     */
    protected function whyRecommendedLine(User $freelancer, Quest $quest, array $breakdown): string
    {
        $parts = [];

        if (($breakdown['location_tier'] ?? '') === 'remote') {
            $parts[] = __('Nationwide online fit');
        } elseif ($breakdown['location_tier'] === 'same_lga') {
            $parts[] = __('Based in your LGA');
        } elseif ($breakdown['location_tier'] === 'same_state') {
            $parts[] = __('Based in your state');
        }

        $level = $this->verificationEngine->effectiveLevel($freelancer);
        if ($level > 0) {
            $parts[] = __('Tier :level', ['level' => $level]);
        }

        if (($breakdown['components']['skills'] ?? 0) >= 80) {
            $parts[] = __('Strong skill fit');
        }

        return $parts !== [] ? implode(' · ', $parts) : __('Category and profile match');
    }

    protected function statsLabel(Quest $quest, int $lga, int $state, int $national, bool $remoteQuest = false): string
    {
        if ($remoteQuest) {
            return __(':total freelancers nationwide match this online brief on skills and category', [
                'total' => $lga + $state + $national,
            ]);
        }

        $lgaName = $quest->localGovernment?->name ?? __('your LGA');
        $stateName = $quest->stateModel?->name ?? __('your state');

        return __(':lga in :lgaName | :state in :stateName | :national across Nigeria', [
            'lga' => $lga,
            'lgaName' => $lgaName,
            'state' => $state,
            'stateName' => $stateName,
            'national' => $national,
        ]);
    }
}
