<?php

namespace App\Services\Matching;

use App\Models\FreelancerMetric;
use App\Models\Quest;
use App\Models\User;
use App\Services\Freelancer\FreelancerProSubscriptionService;
use App\Services\Verification\VerificationEngineService;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

class QuestMatchScoreCalculator
{
    public function __construct(
        protected VerificationEngineService $verificationEngine,
        protected FreelancerProSubscriptionService $freelancerPro,
    ) {}

    /**
     * @return array{
     *   total: float,
     *   quality: array{label: string, stars: int},
     *   passes_skills_gate: bool,
     *   passes_language_gate: bool,
     *   location_tier: string,
     *   components: array<string, float>,
     *   bonuses: array<string, float>,
     *   penalties: array<string, float>,
     *   reasons: list<string>,
     *   breakdown_lines: list<string>,
     * }
     */
    public function score(User $freelancer, Quest $quest, ?FreelancerMetric $metrics = null): array
    {
        $weights = config('quest_matching.weights', []);
        $metrics ??= FreelancerMetric::query()->where('user_id', $freelancer->id)->first();

        $locationTier = 'unknown';
        $passesSkillsGate = true;
        $location = $this->locationScore($freelancer, $quest, $locationTier);
        $skills = $this->skillsScore($freelancer, $quest, $metrics, $passesSkillsGate);
        $budget = $this->budgetAlignmentScore($quest, $metrics);
        $tierQuality = $this->tierQualityScore($freelancer, $metrics);
        $activity = $this->activityScore($metrics);

        $passesLanguageGate = $this->passesLanguageGate($freelancer, $quest);

        $components = [
            'location' => $location,
            'skills' => $skills,
            'budget' => $budget,
            'tier_quality' => $tierQuality,
            'activity' => $activity,
        ];

        $weighted = 0.0;
        foreach ($components as $key => $value) {
            $weighted += $value * (float) ($weights[$key] ?? 0);
        }

        $bonuses = [];
        $penalties = [];

        if ($this->nicheBonusApplies($quest, $metrics)) {
            $bonuses['niche'] = (float) config('quest_matching.niche_specialization_bonus_points', 10);
        }

        if ($this->urgencyBonusApplies($quest, $metrics)) {
            $bonuses['urgency'] = (float) config('quest_matching.urgency_bonus_points', 5);
        }

        // Pro visibility bonus only when the freelancer already passes qualification gates.
        if ($passesSkillsGate && $passesLanguageGate && $this->freelancerPro->isPro($freelancer)) {
            $bonuses['freelancer_pro'] = (float) config('freelancer_pro.match_score_bonus_points', config('quest_matching.freelancer_pro_bonus_points', 8));
        }

        $disputePenalty = $this->disputePenaltyPoints($metrics);
        if ($disputePenalty > 0) {
            $penalties['disputes'] = $disputePenalty;
        }

        $total = min(100, max(0, $weighted + array_sum($bonuses) - array_sum($penalties)));

        $reasons = $this->buildReasons($components, $locationTier, $passesSkillsGate, $passesLanguageGate);
        $breakdownLines = $this->buildBreakdownLines($components, $locationTier);

        return [
            'total' => round($total, 2),
            'quality' => $this->qualityBand($total),
            'passes_skills_gate' => $passesSkillsGate,
            'passes_language_gate' => $passesLanguageGate,
            'location_tier' => $locationTier,
            'components' => $components,
            'bonuses' => $bonuses,
            'penalties' => $penalties,
            'reasons' => $reasons,
            'breakdown_lines' => $breakdownLines,
        ];
    }

    public function locationTierFor(User $freelancer, Quest $quest): string
    {
        $tier = 'unknown';
        $this->locationScore($freelancer, $quest, $tier);

        return $tier;
    }

    /**
     * @param -out  string  $tier
     */
    protected function locationScore(User $freelancer, Quest $quest, string &$tier): float
    {
        $tiers = config('quest_matching.location_tiers', []);
        $freelancerLga = (int) ($freelancer->local_government_id ?? 0);
        $questLga = (int) ($quest->local_government_id ?? 0);
        $freelancerState = (int) ($freelancer->state_id ?? 0);
        $questState = (int) ($quest->state_id ?? 0);

        if ($freelancerLga > 0 && $questLga > 0 && $freelancerLga === $questLga) {
            $tier = 'same_lga';

            return (float) ($tiers['same_lga'] ?? 100);
        }

        if ($freelancerState > 0 && $questState > 0 && $freelancerState === $questState) {
            $tier = 'same_state';

            return (float) ($tiers['same_state'] ?? 70);
        }

        if ($questState > 0 || $questLga > 0) {
            $tier = 'different_state';

            return (float) ($tiers['different_state'] ?? 40);
        }

        $tier = 'unknown';

        return (float) ($tiers['unknown'] ?? 50);
    }

    /**
     * @param -out  bool  $passesGate
     */
    protected function skillsScore(User $freelancer, Quest $quest, ?FreelancerMetric $metrics, bool &$passesGate): float
    {
        $required = $this->normalizeSkillTokens($quest->required_skills ?? []);
        if ($required === []) {
            $passesGate = true;

            return 100.0;
        }

        $owned = $this->freelancerSkillTokens($freelancer, $metrics);
        $matched = count(array_intersect($required, $owned));
        $total = count($required);
        $ratio = $total > 0 ? $matched / $total : 1;
        $minRatio = (float) config('quest_matching.skills_minimum_ratio', 0.5);
        $passesGate = $ratio >= $minRatio;

        return round($ratio * 100, 2);
    }

    protected function budgetAlignmentScore(Quest $quest, ?FreelancerMetric $metrics): float
    {
        $budget = (int) ($quest->budget_amount_minor ?? 0);
        $typical = (int) ($metrics?->typical_job_value_minor ?? 0);

        if ($budget <= 0 || $typical <= 0) {
            return 55.0;
        }

        $ratio = min($budget, $typical) / max($budget, $typical);

        return round($ratio * 100, 2);
    }

    protected function tierQualityScore(User $freelancer, ?FreelancerMetric $metrics): float
    {
        $level = (int) ($metrics?->verification_level ?? $this->verificationEngine->effectiveLevel($freelancer));
        $levelScore = min(100, $level * 20);

        $rating = (float) ($metrics?->average_rating ?? $freelancer->trustMetrics?->avg_rating_as_freelancer ?? 0);
        $ratingScore = $rating > 0 ? min(100, ($rating / 5) * 100) : 50;

        $completion = (float) ($metrics?->completion_rate ?? 0);
        $completionScore = min(100, $completion);

        return round(($levelScore * 0.45) + ($ratingScore * 0.4) + ($completionScore * 0.15), 2);
    }

    protected function activityScore(?FreelancerMetric $metrics): float
    {
        $last = $metrics?->last_proposal_at;
        if (! $last instanceof CarbonInterface) {
            return 40.0;
        }

        $days = $last->diffInDays(now());

        if ($days <= 7) {
            return 100.0;
        }
        if ($days <= 30) {
            return 75.0;
        }

        return max(20, 75 - (($days - 30) * 1.5));
    }

    protected function disputePenaltyPoints(?FreelancerMetric $metrics): float
    {
        $count = (int) ($metrics?->dispute_count_last_6_months ?? 0)
            + (int) ($metrics?->cancellation_count_last_6_months ?? 0);
        if ($count <= 0) {
            return 0.0;
        }

        $per = (float) config('quest_matching.dispute_penalty_per_incident', 8);
        $cap = (float) config('quest_matching.dispute_penalty_cap', 24);

        return min($cap, $count * $per);
    }

    protected function nicheBonusApplies(Quest $quest, ?FreelancerMetric $metrics): bool
    {
        $categoryId = (int) ($quest->quest_category_id ?? 0);
        if ($categoryId <= 0 || $metrics === null) {
            return false;
        }

        $map = $metrics->niche_completions_by_category ?? [];
        $count = (int) ($map[(string) $categoryId] ?? $map[$categoryId] ?? 0);

        return $count >= (int) config('quest_matching.niche_specialization_min_completions', 10);
    }

    protected function urgencyBonusApplies(Quest $quest, ?FreelancerMetric $metrics): bool
    {
        $due = $quest->due_at ?? $quest->estimated_delivery_date;
        if (! $due instanceof CarbonInterface || ! $due->isFuture()) {
            return false;
        }

        $urgencyDays = (int) config('quest_matching.urgency_days', 3);
        if ($due->gt(now()->addDays($urgencyDays))) {
            return false;
        }

        $minQuick = (int) config('quest_matching.urgency_quick_jobs_min', 3);

        return (int) ($metrics?->quick_turnaround_completed_count ?? 0) >= $minQuick;
    }

    /**
     * @return array{label: string, stars: int}
     */
    public function qualityForScore(float $score): array
    {
        return $this->qualityBand($score);
    }

    public function passesLanguageGate(User $freelancer, Quest $quest): bool
    {
        $required = $this->normalizeLanguageCodes($quest->required_languages ?? []);
        if ($required === []) {
            return true;
        }

        $spoken = $this->freelancerLanguageCodes($freelancer);

        return count(array_intersect($required, $spoken)) > 0;
    }

    /**
     * @param  array<string, float>  $components
     * @return list<string>
     */
    protected function buildReasons(array $components, string $locationTier, bool $skillsPass, bool $languagePass): array
    {
        $reasons = [];

        $reasons[] = match ($locationTier) {
            'same_lga' => __('Same LGA as your profile — top local match.'),
            'same_state' => __('Same state — strong regional fit.'),
            'different_state' => __('Different state — still in your category.'),
            default => __('Nationwide listing — location open.'),
        };

        if ($skillsPass && ($components['skills'] ?? 0) >= 80) {
            $reasons[] = __('Your listed skills align with this brief.');
        } elseif (! $skillsPass) {
            $reasons[] = __('Some required skills are missing from your profile.');
        }

        if (($components['budget'] ?? 0) >= 75) {
            $reasons[] = __('Budget is close to your typical project size.');
        }

        if (($components['activity'] ?? 0) >= 90) {
            $reasons[] = __('You have been active on proposals recently.');
        }

        if (! $languagePass) {
            $reasons[] = __('Language requirement not met on your profile.');
        }

        return array_slice($reasons, 0, 4);
    }

    /**
     * @param  array<string, float>  $components
     * @return list<string>
     */
    protected function buildBreakdownLines(array $components, string $locationTier): array
    {
        $lines = [];
        $lines[] = ($components['location'] ?? 0) >= 70
            ? '✓ '.__('Your location')
            : '○ '.__('Location stretch');

        $lines[] = ($components['skills'] ?? 0) >= 50
            ? '✓ '.__('Your skills')
            : '○ '.__('Skills gap');

        $lines[] = ($components['budget'] ?? 0) >= 60
            ? '✓ '.__('Your budget range')
            : '○ '.__('Budget stretch');

        $lines[] = ($components['tier_quality'] ?? 0) >= 60
            ? '✓ '.__('Tier & profile quality')
            : '○ '.__('Build profile strength');

        $lines[] = ($components['activity'] ?? 0) >= 60
            ? '✓ '.__('Active recently')
            : '○ '.__('Send proposals to stay visible');

        if ($locationTier === 'same_lga') {
            $lines[0] = '✓ '.__('Same LGA');
        }

        return $lines;
    }

    /**
     * @return array{label: string, stars: int}
     */
    protected function qualityBand(float $score): array
    {
        foreach (config('quest_matching.match_quality_bands', []) as $band) {
            if ($score >= (float) ($band['min'] ?? 0)) {
                return [
                    'label' => (string) ($band['label'] ?? 'Match'),
                    'stars' => (int) ($band['stars'] ?? 1),
                ];
            }
        }

        return ['label' => __('Match'), 'stars' => 1];
    }

    /**
     * @param  list<mixed>  $skills
     * @return list<string>
     */
    protected function normalizeSkillTokens(array $skills): array
    {
        return collect($skills)
            ->flatMap(fn ($s) => is_string($s) ? preg_split('/[,;|]+/', $s) : [])
            ->map(fn ($s) => $this->tokenize((string) $s))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function freelancerSkillTokens(User $freelancer, ?FreelancerMetric $metrics): array
    {
        $fromMetrics = $this->normalizeSkillTokens($metrics?->skills_list ?? []);
        if ($fromMetrics !== []) {
            return $fromMetrics;
        }

        $settings = $freelancer->public_profile_settings ?? [];
        $fromSettings = $this->normalizeSkillTokens(Arr::wrap($settings['skills'] ?? []));

        if ($fromSettings !== []) {
            return $fromSettings;
        }

        $text = implode(' ', array_filter([
            $freelancer->headline,
            $freelancer->profession,
            $freelancer->bio,
        ]));

        return $this->normalizeSkillTokens(preg_split('/\s+/', $text) ?: []);
    }

    /**
     * @param  list<mixed>  $languages
     * @return list<string>
     */
    protected function normalizeLanguageCodes(array $languages): array
    {
        return collect($languages)
            ->map(fn ($l) => strtolower(trim((string) $l)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function freelancerLanguageCodes(User $freelancer): array
    {
        $codes = [];
        if ($freelancer->locale) {
            $codes[] = strtolower((string) $freelancer->locale);
        }

        $settings = $freelancer->public_profile_settings ?? [];
        foreach (Arr::wrap($settings['languages'] ?? []) as $lang) {
            $codes[] = strtolower(trim((string) $lang));
        }

        return array_values(array_unique(array_filter($codes)));
    }

    protected function tokenize(string $value): string
    {
        $value = strtolower(trim($value));

        return $value === '' ? '' : preg_replace('/\s+/', ' ', $value) ?? '';
    }
}
