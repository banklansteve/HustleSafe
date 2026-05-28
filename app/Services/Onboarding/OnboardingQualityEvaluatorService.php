<?php

namespace App\Services\Onboarding;

use App\Models\LoginEvent;
use App\Models\OnboardingQualityReview;
use App\Models\User;
use Illuminate\Support\Str;

class OnboardingQualityEvaluatorService
{
    /**
     * @return array{completeness_score: int, auto_flags: list<array{key: string, label: string, severity: string, message: string, dismissed: bool}>}
     */
    public function evaluate(User $user): array
    {
        $isFreelancer = $this->isFreelancer($user);
        $flags = [];

        $photo = $this->evaluatePhoto($user);
        if ($photo !== null) {
            $flags[] = $photo;
        }

        $bio = $this->evaluateBio($user);
        if ($bio !== null) {
            $flags[] = $bio;
        }

        if ($isFreelancer) {
            $skills = $this->evaluateSkillConsistency($user);
            if ($skills !== null) {
                $flags[] = $skills;
            }

            $portfolio = $this->evaluatePortfolio($user);
            if ($portfolio !== null) {
                $flags[] = $portfolio;
            }

            $categories = $this->evaluateCategories($user);
            if ($categories !== null) {
                $flags[] = $categories;
            }
        }

        $location = $this->evaluateLocation($user);
        if ($location !== null) {
            $flags[] = $location;
        }

        $behaviour = $this->evaluateBehaviour($user);
        if ($behaviour !== null) {
            $flags[] = $behaviour;
        }

        $score = $this->completenessScore($user, $isFreelancer, $flags);

        return [
            'completeness_score' => $score,
            'auto_flags' => $flags,
        ];
    }

    public function applyToReview(OnboardingQualityReview $review, User $user): OnboardingQualityReview
    {
        $evaluation = $this->evaluate($user);
        $overrides = is_array($review->manual_flag_overrides) ? $review->manual_flag_overrides : [];
        $flags = $this->mergeOverrides($evaluation['auto_flags'], $overrides);

        $review->forceFill([
            'completeness_score' => $evaluation['completeness_score'],
            'auto_flags' => $flags,
            'last_evaluated_at' => now(),
        ])->save();

        return $review->refresh();
    }

    /**
     * @param  list<array{key: string, label: string, severity: string, message: string, dismissed: bool}>  $flags
     * @param  array<string, mixed>  $overrides
     * @return list<array{key: string, label: string, severity: string, message: string, dismissed: bool}>
     */
    public function mergeOverrides(array $flags, array $overrides): array
    {
        return collect($flags)
            ->map(function (array $flag) use ($overrides) {
                $override = $overrides[$flag['key']] ?? null;
                if (is_array($override) && array_key_exists('dismissed', $override)) {
                    $flag['dismissed'] = (bool) $override['dismissed'];
                }

                return $flag;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function activeFlagLabels(array $flags): array
    {
        return collect($flags)
            ->filter(fn (array $flag) => ! ($flag['dismissed'] ?? false))
            ->map(fn (array $flag) => [
                'key' => $flag['key'],
                'label' => $flag['label'],
            ])
            ->values()
            ->all();
    }

    private function isFreelancer(User $user): bool
    {
        $slug = $user->role?->slug ?? $user->account_type;

        return in_array($slug, ['freelancer', 'seller', 'provider'], true);
    }

    /**
     * @return array{key: string, label: string, severity: string, message: string, dismissed: bool}|null
     */
    private function evaluatePhoto(User $user): ?array
    {
        $url = strtolower(trim((string) $user->avatar_url));

        if ($url === '') {
            return $this->flag('photo_missing', 'Photo issue', 'high', 'No profile photo uploaded.', false);
        }

        foreach (config('onboarding_quality.stock_photo_url_patterns', []) as $pattern) {
            if (str_contains($url, strtolower((string) $pattern))) {
                return $this->flag('photo_stock', 'Photo issue', 'high', 'Avatar URL looks like a placeholder or stock image.', false);
            }
        }

        if (preg_match('/\.(svg|ico)(\?|$)/i', $url)) {
            return $this->flag('photo_logo', 'Photo issue', 'medium', 'Avatar may be a logo or icon rather than a person.', false);
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, severity: string, message: string, dismissed: bool}|null
     */
    private function evaluateBio(User $user): ?array
    {
        $bio = trim((string) $user->bio);
        $headline = trim((string) $user->headline);

        if ($bio === '' && $headline === '') {
            return $this->flag('bio_empty', 'Generic bio', 'high', 'Bio and headline are both empty.', false);
        }

        $text = strtolower($bio !== '' ? $bio : $headline);

        if (strlen($text) < 24) {
            return $this->flag('bio_short', 'Generic bio', 'medium', 'Bio/headline is very short.', false);
        }

        foreach (config('onboarding_quality.generic_bio_phrases', []) as $phrase) {
            if (str_contains($text, strtolower((string) $phrase))) {
                return $this->flag('bio_generic', 'Generic bio', 'medium', 'Bio contains generic placeholder wording.', false);
            }
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, severity: string, message: string, dismissed: bool}|null
     */
    private function evaluateSkillConsistency(User $user): ?array
    {
        $profession = strtolower(trim((string) $user->profession));
        $headline = strtolower(trim((string) $user->headline));
        $leafCount = $user->relationLoaded('questCategoryPreferences')
            ? $user->questCategoryPreferences->count()
            : $user->questCategoryPreferences()->count();

        if ($profession === '' && $headline === '') {
            return $this->flag('skills_missing', 'Missing skills', 'high', 'Profession and headline are empty for a freelancer profile.', false);
        }

        if ($leafCount < 1) {
            return $this->flag('skills_categories_gap', 'Missing skills', 'medium', 'Work categories are not selected while professional details are present.', false);
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, severity: string, message: string, dismissed: bool}|null
     */
    private function evaluatePortfolio(User $user): ?array
    {
        $count = $user->relationLoaded('portfolios')
            ? $user->portfolios->count()
            : $user->portfolios()->count();

        if ($count < 1) {
            return $this->flag('portfolio_missing', 'Portfolio', 'medium', 'No portfolio items uploaded.', false);
        }

        if ($count < 2) {
            return $this->flag('portfolio_thin', 'Portfolio', 'low', 'Only one portfolio item — consider requesting more samples.', false);
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, severity: string, message: string, dismissed: bool}|null
     */
    private function evaluateCategories(User $user): ?array
    {
        $leafCount = $user->relationLoaded('questCategoryPreferences')
            ? $user->questCategoryPreferences->count()
            : $user->questCategoryPreferences()->count();

        if ($leafCount < 1) {
            return $this->flag('categories_missing', 'Categories', 'high', 'No work subcategories selected.', false);
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, severity: string, message: string, dismissed: bool}|null
     */
    private function evaluateLocation(User $user): ?array
    {
        if (trim((string) $user->address_line) === '' || $user->state_id === null || $user->local_government_id === null) {
            return $this->flag('location_incomplete', 'Location', 'medium', 'Structured address, state, or LGA is incomplete.', false);
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, severity: string, message: string, dismissed: bool}|null
     */
    private function evaluateBehaviour(User $user): ?array
    {
        $recentIp = LoginEvent::query()
            ->where('user_id', $user->id)
            ->latest('logged_in_at')
            ->value('ip_address');

        if ($recentIp === null || $user->created_at === null) {
            return null;
        }

        $sameIpSignups = User::query()
            ->where('id', '!=', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereHas('loginEvents', fn ($q) => $q->where('ip_address', $recentIp))
            ->count();

        if ($sameIpSignups >= 2) {
            return $this->flag('behaviour_shared_ip', 'Suspicious behaviour', 'high', "Shared signup IP ({$recentIp}) with {$sameIpSignups} other new accounts in 7 days.", false);
        }

        return null;
    }

    /**
     * @param  list<array{key: string, label: string, severity: string, message: string, dismissed: bool}>  $flags
     */
    private function completenessScore(User $user, bool $isFreelancer, array $flags): int
    {
        $base = (int) ($user->profile_completion_percent ?? 0);
        $activeFlags = collect($flags)->filter(fn (array $f) => ! ($f['dismissed'] ?? false));

        $penalty = (int) $activeFlags->sum(fn (array $f) => match ($f['severity'] ?? 'low') {
            'high' => 18,
            'medium' => 10,
            default => 5,
        });

        $score = max(0, min(100, $base - $penalty));

        if ($isFreelancer && $user->questCategoryPreferences()->count() < 1) {
            $score = min($score, 55);
        }

        return $score;
    }

    /**
     * @return array{key: string, label: string, severity: string, message: string, dismissed: bool}
     */
    private function flag(string $key, string $label, string $severity, string $message, bool $dismissed): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'severity' => $severity,
            'message' => $message,
            'dismissed' => $dismissed,
        ];
    }
}
