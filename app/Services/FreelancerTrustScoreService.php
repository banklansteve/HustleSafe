<?php

namespace App\Services;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserTrustMetric;

class FreelancerTrustScoreService
{
    public function __construct(
        protected VerificationTrustComponentService $verification,
    ) {}

    /**
     * @return array{score: int, breakdown: array<string, float|int>}
     */
    public function calculate(User $user): array
    {
        $weights = config('scoring.freelancer.weights', []);
        $capDays = (int) config('scoring.freelancer.account_age_cap_days', 730);
        $neutralDispute = (float) config('scoring.freelancer.neutral_dispute_component', 0.85);
        $neutralOnTime = (float) config('scoring.freelancer.neutral_on_time_component', 0.85);

        $ratingNorm = ($user->avg_rating_as_freelancer !== null)
            ? ($user->avg_rating_as_freelancer / 5)
            : 0.6;

        $profileNorm = ($user->profile_completion_percent ?? 0) / 100;

        $ageDays = $user->created_at?->diffInDays(now()) ?? 0;
        $ageNorm = min(1, $ageDays / max(1, $capDays));

        $finished = Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', QuestStatus::reviewEligibleStatuses())
            ->count();

        $disputed = Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', QuestStatus::reviewEligibleStatuses())
            ->where(function ($q): void {
                $q->where('dispute_opened', true)
                    ->orWhere('status', QuestStatus::InDispute);
            })
            ->count();

        $disputeInverse = $finished > 0
            ? max(0, 1 - ($disputed / $finished))
            : $neutralDispute;

        $onTimeRelevant = Quest::query()
            ->where('freelancer_id', $user->id)
            ->where('status', QuestStatus::Completed)
            ->whereNotNull('completed_on_time')
            ->count();

        $onTimeHits = Quest::query()
            ->where('freelancer_id', $user->id)
            ->where('status', QuestStatus::Completed)
            ->where('completed_on_time', true)
            ->count();

        $onTimeNorm = $onTimeRelevant > 0
            ? $onTimeHits / $onTimeRelevant
            : $neutralOnTime;

        $emailNorm = $this->verification->emailVerifiedNorm($user);
        $identityNorm = $this->verification->identityVerifiedNorm($user);
        $addressNorm = $this->verification->addressVerifiedNorm($user);
        $qualNorm = $this->verification->qualificationsVerifiedNorm($user);
        $cacNorm = $this->verification->cacVerifiedNorm($user);

        $linear =
            ($weights['average_rating'] ?? 0) * $ratingNorm
            + ($weights['profile_completion'] ?? 0) * $profileNorm
            + ($weights['account_age'] ?? 0) * $ageNorm
            + ($weights['dispute_inverse'] ?? 0) * $disputeInverse
            + ($weights['on_time_delivery'] ?? 0) * $onTimeNorm
            + ($weights['email_verified'] ?? 0) * $emailNorm
            + ($weights['identity_verified'] ?? 0) * $identityNorm
            + ($weights['address_verified'] ?? 0) * $addressNorm
            + ($weights['qualifications_verified'] ?? 0) * $qualNorm
            + ($weights['cac_verified'] ?? 0) * $cacNorm;

        $user->loadMissing('trustMetrics');
        $penalty = (int) ($user->trustMetrics?->reliability_penalty_points ?? 0);
        $score = (int) round(min(100, max(0, ($linear * 100) - min(25, $penalty))));

        return [
            'score' => $score,
            'breakdown' => [
                'rating_norm' => round($ratingNorm, 4),
                'profile_norm' => round($profileNorm, 4),
                'age_norm' => round($ageNorm, 4),
                'dispute_inverse' => round($disputeInverse, 4),
                'on_time_norm' => round($onTimeNorm, 4),
                'email_verified' => round($emailNorm, 4),
                'identity_verified' => round($identityNorm, 4),
                'address_verified' => round($addressNorm, 4),
                'qualifications_verified' => round($qualNorm, 4),
                'cac_verified' => round($cacNorm, 4),
                'linear_raw' => round($linear, 4),
                'reliability_penalty' => $penalty,
                'quests_finished' => $finished,
                'quests_disputed' => $disputed,
            ],
        ];
    }

    public function sync(User $user): void
    {
        $result = $this->calculate($user);

        UserTrustMetric::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'freelancer_trust_score' => $result['score'],
                'last_recomputed_at' => now(),
            ]
        );
    }
}
