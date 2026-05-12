<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTrustMetric;

class TrustScoreOrchestrator
{
    public function __construct(
        protected ProfileCompletionService $profileCompletion,
        protected FreelancerTrustScoreService $freelancerTrustScore,
        protected ClientTrustScoreService $clientTrustScore,
        protected RatingAggregationService $ratingAggregation,
    ) {}

    public function recalculate(User $user): void
    {
        UserTrustMetric::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'freelancer_trust_score' => 0,
                'client_trust_score' => 50,
                'profile_completion_percent' => 0,
            ]
        );

        $reload = function () use ($user): void {
            $user->refresh();
            $user->load('trustMetrics');
        };

        $this->profileCompletion->sync($user);
        $reload();
        $this->ratingAggregation->syncForUser($user);
        $reload();
        $this->freelancerTrustScore->sync($user);
        $reload();
        $this->clientTrustScore->sync($user);
        $reload();
    }
}
