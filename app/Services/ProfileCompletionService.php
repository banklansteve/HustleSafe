<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTrustMetric;

/**
 * Computes profile completeness (0–100) from filled core fields — weights adjustable here.
 */
class ProfileCompletionService
{
    /**
     * @var array<string, float>
     */
    protected array $freelancerWeights = [
        'first_name' => 0.07,
        'last_name' => 0.07,
        'phone' => 0.12,
        'state_id' => 0.08,
        'local_government_id' => 0.06,
        'city' => 0.06,
        'address_line' => 0.07,
        'profession' => 0.12,
        'headline' => 0.10,
        'bio' => 0.15,
        'hourly_rate_min' => 0.075,
        'hourly_rate_max' => 0.065,
        'years_experience' => 0.05,
        'avatar_url' => 0.08,
    ];

    /**
     * @var array<string, float>
     */
    protected array $clientWeights = [
        'first_name' => 0.10,
        'last_name' => 0.10,
        'phone' => 0.14,
        'company_name' => 0.12,
        'address_line' => 0.11,
        'city' => 0.06,
        'state_id' => 0.10,
        'local_government_id' => 0.08,
        'job_title' => 0.10,
        'avatar_url' => 0.14,
    ];

    public function percent(User $user): int
    {
        $slug = $user->role?->slug;
        $weights = $slug === 'freelancer' ? $this->freelancerWeights : $this->clientWeights;

        $sum = array_sum($weights) ?: 1;
        $total = 0.0;
        foreach ($weights as $field => $weight) {
            if ($this->fieldFilled($user, $field)) {
                $total += $weight / $sum;
            }
        }

        return (int) round(min(100, max(0, $total * 100)));
    }

    public function sync(User $user): void
    {
        UserTrustMetric::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['profile_completion_percent' => $this->percent($user)]
        );
    }

    protected function fieldFilled(User $user, string $field): bool
    {
        $v = $user->getAttribute($field);

        return $v !== null && $v !== '';
    }
}
