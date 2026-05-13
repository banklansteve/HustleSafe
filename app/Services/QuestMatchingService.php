<?php

namespace App\Services;

use App\Enums\QuestStatus;
use App\Enums\QuestVisibility;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Support\Collection;

/**
 * Ranks open quests for a freelancer using category fit, geography, and freshness.
 * Designed to be extended (weights in config, ML layer, client trust signals, etc.).
 */
class QuestMatchingService
{
    /**
     * @return Collection<int, array{quest: Quest, match_score: int, reasons: list<string>}>
     */
    public function rankedOpenQuestsForFreelancer(User $freelancer, int $limit = 12): Collection
    {
        $prefs = $freelancer->questCategoryPreferences()->get(['quest_categories.id', 'quest_categories.parent_id']);
        if ($prefs->isEmpty()) {
            return $this->fallbackOpenQuests($limit);
        }

        $prefIds = $prefs->pluck('id')->all();
        $parentIds = $prefs->pluck('parent_id')->filter()->unique()->all();

        $quests = Quest::query()
            ->where('status', QuestStatus::Open)
            ->where('visibility', QuestVisibility::Public)
            ->whereNull('freelancer_id')
            ->with(['questCategory:id,parent_id,name', 'stateModel:id,name', 'client:id,first_name,name'])
            ->latest('created_at')
            ->limit(200)
            ->get();

        $scored = $quests->map(function (Quest $quest) use ($freelancer, $prefIds, $parentIds): array {
            $reasons = [];
            $raw = 0.0;

            $catScore = $this->categoryScore($quest, $prefIds, $parentIds, $reasons);
            $raw += $catScore;

            $geoScore = $this->geoScore($quest, $freelancer, $reasons);
            $raw += $geoScore;

            $raw += $this->freshnessScore($quest, $reasons);

            $budgetScore = $this->budgetFitScore($quest, $freelancer, $reasons);
            $raw += $budgetScore;

            $raw += $this->clientFollowBoost($quest, $freelancer, $reasons);

            $match = (int) round(min(100, max(0, $raw)));

            return [
                'quest' => $quest,
                'match_score' => $match,
                'reasons' => $reasons,
            ];
        });

        return $scored
            ->sortByDesc(fn (array $row) => [$row['match_score'], $row['quest']->created_at?->timestamp ?? 0])
            ->values()
            ->take($limit);
    }

    /**
     * @param  list<string>  $reasons
     */
    protected function categoryScore(Quest $quest, array $prefIds, array $parentIds, array &$reasons): float
    {
        $cid = $quest->quest_category_id;
        if ($cid === null) {
            $reasons[] = __('Broad listing — add categories to quests for sharper matches.');

            return 12;
        }

        if (in_array($cid, $prefIds, true)) {
            $reasons[] = __('Matches your selected specialty.');

            return 48;
        }

        $questCat = $quest->questCategory;
        if ($questCat && $questCat->parent_id !== null && in_array($questCat->parent_id, $parentIds, true)) {
            $reasons[] = __('Same field as your skills — related specialty.');

            return 28;
        }

        $reasons[] = __('Outside your saved skills — still visible.');

        return 6;
    }

    /**
     * @param  list<string>  $reasons
     */
    protected function geoScore(Quest $quest, User $freelancer, array &$reasons): float
    {
        $uLat = $freelancer->latitude;
        $uLng = $freelancer->longitude;
        $qLat = $quest->latitude;
        $qLng = $quest->longitude;

        if ($uLat !== null && $uLng !== null && $qLat !== null && $qLng !== null) {
            $km = $this->haversineKm((float) $uLat, (float) $uLng, (float) $qLat, (float) $qLng);
            if ($km <= 25) {
                $reasons[] = __('Very close to your location.');

                return 34;
            }
            if ($km <= 80) {
                $reasons[] = __('Within your region.');

                return 26;
            }
            if ($km <= 250) {
                $reasons[] = __('Within Nigeria-wide radius.');

                return 16;
            }
            $reasons[] = __('Farther away — remote-friendly?');

            return 8;
        }

        if ($quest->state_id !== null && $freelancer->state_id !== null && (int) $quest->state_id === (int) $freelancer->state_id) {
            $reasons[] = __('Same state as your profile.');

            return 28;
        }

        if ($quest->city && $freelancer->city && strcasecmp(trim((string) $quest->city), trim((string) $freelancer->city)) === 0) {
            $reasons[] = __('Same city hint in listing.');

            return 24;
        }

        $reasons[] = __('Location not specified — nationwide.');

        return 14;
    }

    /**
     * @param  list<string>  $reasons
     */
    protected function freshnessScore(Quest $quest, array &$reasons): float
    {
        $ageHours = $quest->created_at?->diffInHours(now()) ?? 999;
        if ($ageHours <= 48) {
            $reasons[] = __('Posted recently.');

            return 14;
        }
        if ($ageHours <= 168) {
            return 8;
        }

        return 4;
    }

    /**
     * @param  list<string>  $reasons
     */
    protected function budgetFitScore(Quest $quest, User $freelancer, array &$reasons): float
    {
        $budget = $quest->budget_amount_minor;
        $min = $freelancer->hourly_rate_min;
        if ($budget === null || $min === null) {
            return 4;
        }

        $implied = (float) $budget / 100 / 8;
        if ($implied >= (float) $min * 0.85) {
            $reasons[] = __('Budget aligns with your rate floor.');

            return 10;
        }

        return 2;
    }

    /**
     * Sponsors who follow a freelancer get their open quests boosted for that freelancer.
     *
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

        return 24;
    }

    protected function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * @return Collection<int, array{quest: Quest, match_score: int, reasons: list<string>}>
     */
    protected function fallbackOpenQuests(int $limit): Collection
    {
        $quests = Quest::query()
            ->where('status', QuestStatus::Open)
            ->where('visibility', QuestVisibility::Public)
            ->whereNull('freelancer_id')
            ->with(['questCategory:id,parent_id,name', 'stateModel:id,name', 'client:id,first_name,name'])
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return $quests->map(fn (Quest $q) => [
            'quest' => $q,
            'match_score' => 35,
            'reasons' => [__('Add work categories in your profile to unlock smarter matches.')],
        ]);
    }
}
