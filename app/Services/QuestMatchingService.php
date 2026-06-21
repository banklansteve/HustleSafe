<?php

namespace App\Services;

use App\Enums\AdminQuestStatus;
use App\Enums\QuestStatus;
use App\Enums\QuestVisibility;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\User;
use App\Models\UserFollow;
use App\Services\Admin\QuestBoostService;
use App\Services\Matching\FreelancerMetricsService;
use App\Services\Matching\QuestMatchScoreCalculator;
use App\Services\Matching\QuestRemoteMatchPolicy;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Support\Collection;

/**
 * Ranks open quests for freelancers: exact category gate, tier/budget/active-job gates,
 * then weighted scoring. On-site jobs weight location heavily; online-friendly jobs
 * score on skills, budget, tier quality, and activity instead.
 */
class QuestMatchingService
{
    public function __construct(
        protected QuestMatchScoreCalculator $scoreCalculator,
        protected FreelancerMetricsService $metricsService,
        protected VerificationEngineService $verificationEngine,
        protected QuestRemoteMatchPolicy $remoteMatchPolicy,
    ) {}

    /**
     * @return Collection<int, array{
     *   quest: Quest,
     *   match_score: int,
     *   match_quality: array{label: string, stars: int},
     *   match_breakdown: list<string>,
     *   location_tier: string,
     *   reasons: list<string>,
     * }>
     */
    public function rankedOpenQuestsForFreelancer(User $freelancer, int $limit = 12): Collection
    {
        $prefIds = array_values(array_unique(array_map(
            'intval',
            $freelancer->questCategoryPreferences()->pluck('quest_categories.id')->all()
        )));
        if ($prefIds === []) {
            return $this->fallbackOpenQuests($limit);
        }

        if ($this->activeJobCount($freelancer) >= $this->activeJobLimit($freelancer)) {
            return collect();
        }

        $proposalLimit = $this->verificationEngine->freelancerProposalLimitMinor($freelancer);
        $metrics = $this->metricsService->forUser($freelancer);
        $candidateLimit = (int) config('quest_matching.freelancer_feed_candidate_limit', 300);

        $quests = Quest::query()
            ->where('status', QuestStatus::Open)
            ->where(fn ($query) => $query->whereNull('admin_status')->orWhere('admin_status', '<>', AdminQuestStatus::Suspended->value))
            ->where('visibility', QuestVisibility::Public)
            ->whereNull('freelancer_id')
            ->whereIn('quest_category_id', $prefIds)
            ->when($proposalLimit > 0, function ($query) use ($proposalLimit): void {
                $query->where(function ($inner) use ($proposalLimit): void {
                    $inner->whereNull('budget_amount_minor')
                        ->orWhere('budget_amount_minor', '<=', $proposalLimit);
                });
            })
            ->with([
                'questCategory:id,parent_id,name',
                'questCategory.parent:id,name',
                'stateModel:id,name',
                'localGovernment:id,name',
                'client:id,first_name,name',
            ])
            ->latest('created_at')
            ->limit($candidateLimit)
            ->get();

        $scored = $quests->map(function (Quest $quest) use ($freelancer, $metrics): ?array {
            $breakdown = $this->scoreCalculator->score($freelancer, $quest, $metrics);

            if (! $breakdown['passes_skills_gate'] || ! $breakdown['passes_language_gate']) {
                return null;
            }

            $reasons = $breakdown['reasons'];
            $followBoost = $this->clientFollowBoost($quest, $freelancer, $reasons);
            $total = min(100, $breakdown['total'] + $followBoost);

            return [
                'quest' => $quest,
                'match_score' => (int) round($total),
                'match_quality' => $this->scoreCalculator->qualityForScore($total),
                'match_breakdown' => $breakdown['breakdown_lines'],
                'location_tier' => $breakdown['location_tier'],
                'reasons' => $reasons,
            ];
        })->filter();

        $scored = $this->dedupeScoredRows($scored);

        return $scored
            ->sortByDesc(fn (array $row) => $this->sortKeysForRankedQuest($row))
            ->values()
            ->pipe(fn (Collection $sorted) => $this->prioritizeBoostedQuests($sorted))
            ->take($limit);
    }

    /**
     * @param  array{quest: Quest, match_score: int, location_tier: string}  $row
     * @return list<int|float>
     */
    protected function sortKeysForRankedQuest(array $row): array
    {
        $quest = $row['quest'];

        if ($this->remoteMatchPolicy->isLocationAgnostic($quest)) {
            return [
                (int) ($row['match_score'] ?? 0),
                $quest->created_at?->timestamp ?? 0,
            ];
        }

        return [
            $this->locationTierRank($row['location_tier']),
            (int) ($row['match_score'] ?? 0),
            $quest->created_at?->timestamp ?? 0,
        ];
    }

    /**
     * @return Collection<int, array{quest: Quest, match_score: int, reasons: list<string>, match_quality?: array, match_breakdown?: list<string>}>
     */
    public function discoveryFeedForExplore(User $user, int $limit = 48): Collection
    {
        if ($user->role?->slug === 'freelancer') {
            return $this->rankedOpenQuestsForFreelancer($user, $limit);
        }

        return $this->fallbackOpenQuests($limit)->map(function (array $row) use ($user): array {
            if ($user->role?->slug === 'client') {
                return [
                    'quest' => $row['quest'],
                    'match_score' => 0,
                    'match_quality' => ['label' => '', 'stars' => 0],
                    'match_breakdown' => [],
                    'location_tier' => 'unknown',
                    'reasons' => [__('Public marketplace listings — useful for benchmarking scope and budgets.')],
                ];
            }

            return $row;
        });
    }

    /**
     * Location-first ordering within the scored set (LGA → state → national).
     */
    protected function locationTierRank(string $tier): int
    {
        return match ($tier) {
            'same_lga' => 3,
            'same_state' => 2,
            'different_state' => 1,
            'remote' => 0,
            default => 0,
        };
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

        return (int) ($map[$level] ?? 2);
    }

    /**
     * @param  Collection<int, array{quest: Quest, match_score: int, reasons: list<string>}>  $sorted
     * @return Collection<int, array{quest: Quest, match_score: int, reasons: list<string>}>
     */
    protected function prioritizeBoostedQuests(Collection $sorted): Collection
    {
        $boostedIds = collect(app(QuestBoostService::class)->activeBoostedQuestIds())
            ->flip();

        if ($boostedIds->isEmpty()) {
            return $sorted;
        }

        $boosted = $sorted->filter(fn (array $row) => $boostedIds->has($row['quest']->id));
        $regular = $sorted->reject(fn (array $row) => $boostedIds->has($row['quest']->id));

        $boostedOrdered = $boosted->sortBy(function (array $row) {
            $endsAt = QuestBoost::query()
                ->activeNow()
                ->where('quest_id', $row['quest']->id)
                ->value('ends_at');

            return $endsAt?->timestamp ?? PHP_INT_MAX;
        })->values();

        return $this->dedupeScoredRows($boostedOrdered->concat($regular->values()));
    }

    /**
     * Keep one row per quest (highest score wins) so sibling category prefs cannot duplicate listings.
     *
     * @param  Collection<int, array{quest: Quest, match_score: int, reasons: list<string>}>  $rows
     * @return Collection<int, array{quest: Quest, match_score: int, reasons: list<string>}>
     */
    protected function dedupeScoredRows(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => (int) $row['quest']->id)
            ->map(function (Collection $group): array {
                return $group->sortByDesc(fn (array $row) => [
                    (int) ($row['match_score'] ?? 0),
                    $row['quest']->created_at?->timestamp ?? 0,
                ])->first();
            })
            ->values();
    }

    /**
     * @param  list<string>  $reasons
     */
    protected function clientFollowBoost(Quest $quest, User $freelancer, array &$reasons): float
    {
        if ($quest->client_id === null) {
            return 0;
        }

        $follows = UserFollow::query()
            ->where('follower_id', $quest->client_id)
            ->where('following_id', $freelancer->id)
            ->exists();

        if (! $follows) {
            return 0;
        }

        $reasons[] = __('This sponsor follows you — your match is prioritised.');

        return (float) config('quest_matching.client_follow_boost_points', 8);
    }

    /**
     * @return Collection<int, array{quest: Quest, match_score: int, reasons: list<string>, match_quality: array, match_breakdown: list<string>, location_tier: string}>
     */
    protected function fallbackOpenQuests(int $limit): Collection
    {
        $quests = Quest::query()
            ->where('status', QuestStatus::Open)
            ->where(fn ($query) => $query->whereNull('admin_status')->orWhere('admin_status', '<>', AdminQuestStatus::Suspended->value))
            ->where('visibility', QuestVisibility::Public)
            ->whereNull('freelancer_id')
            ->with(['questCategory:id,parent_id,name', 'questCategory.parent:id,name', 'stateModel:id,name', 'localGovernment:id,name', 'client:id,first_name,name'])
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return $quests->map(fn (Quest $q) => [
            'quest' => $q,
            'match_score' => 35,
            'match_quality' => ['label' => __('Possible match'), 'stars' => 2],
            'match_breakdown' => [__('Add work categories in your profile to unlock smarter matches.')],
            'location_tier' => 'unknown',
            'reasons' => [__('Add work categories in your profile to unlock smarter matches.')],
        ]);
    }
}
