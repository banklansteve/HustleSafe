<?php

namespace App\Services;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserTrustMetric;

class ClientTrustScoreService
{
    public function __construct(
        protected VerificationTrustComponentService $verification,
    ) {}

    /**
     * @return array{score: int, breakdown: array<string, float|int>}
     */
    public function calculate(User $user): array
    {
        $weights = config('scoring.client.weights', []);
        $capDays = (int) config('scoring.client.account_age_cap_days', 730);
        $neutralDispute = (float) config('scoring.client.neutral_dispute_component', 0.88);
        $neutralSmooth = (float) config('scoring.client.neutral_smooth_closure', 0.88);

        $ratingNorm = ($user->avg_rating_as_client !== null)
            ? ($user->avg_rating_as_client / 5)
            : 0.62;

        $profileNorm = ($user->profile_completion_percent ?? 0) / 100;

        $ageDays = $user->created_at?->diffInDays(now()) ?? 0;
        $ageNorm = min(1, $ageDays / max(1, $capDays));

        $terminal = Quest::query()
            ->where('client_id', $user->id)
            ->whereIn('status', QuestStatus::reviewEligibleStatuses())
            ->count();

        $disputed = Quest::query()
            ->where('client_id', $user->id)
            ->whereIn('status', QuestStatus::reviewEligibleStatuses())
            ->where(function ($q): void {
                $q->where('dispute_opened', true)
                    ->orWhere('status', QuestStatus::InDispute);
            })
            ->count();

        $disputeInverse = $terminal > 0
            ? max(0, 1 - ($disputed / $terminal))
            : $neutralDispute;

        $smooth = Quest::query()
            ->where('client_id', $user->id)
            ->whereIn('status', [
                QuestStatus::Completed,
                QuestStatus::Closed,
                QuestStatus::Archived,
                QuestStatus::CancelledMutual,
                QuestStatus::CancelledByAdmin,
                QuestStatus::WithdrawnByClient,
                QuestStatus::WithdrawnByFreelancer,
            ])
            ->where('dispute_opened', false)
            ->count();

        $smoothNorm = $terminal > 0 ? ($smooth / $terminal) : $neutralSmooth;

        $emailNorm = $this->verification->emailVerifiedNorm($user);
        $identityNorm = $this->verification->identityVerifiedNorm($user);
        $addressNorm = $this->verification->addressVerifiedNorm($user);

        $linear =
            ($weights['average_rating'] ?? 0) * $ratingNorm
            + ($weights['profile_completion'] ?? 0) * $profileNorm
            + ($weights['account_age'] ?? 0) * $ageNorm
            + ($weights['dispute_inverse'] ?? 0) * $disputeInverse
            + ($weights['smooth_closure'] ?? 0) * $smoothNorm
            + ($weights['email_verified'] ?? 0) * $emailNorm
            + ($weights['identity_verified'] ?? 0) * $identityNorm
            + ($weights['address_verified'] ?? 0) * $addressNorm;

        $user->loadMissing('trustMetrics');
        $ghostPenalty = min(15, (int) ($user->trustMetrics?->client_proposal_ghost_strikes ?? 0) * 5);
        $score = (int) round(min(100, max(0, ($linear * 100) - $ghostPenalty)));

        return [
            'score' => $score,
            'breakdown' => [
                'rating_norm' => round($ratingNorm, 4),
                'profile_norm' => round($profileNorm, 4),
                'age_norm' => round($ageNorm, 4),
                'dispute_inverse' => round($disputeInverse, 4),
                'smooth_closure' => round($smoothNorm, 4),
                'email_verified' => round($emailNorm, 4),
                'identity_verified' => round($identityNorm, 4),
                'address_verified' => round($addressNorm, 4),
                'linear_raw' => round($linear, 4),
                'ghost_strike_penalty' => $ghostPenalty,
                'terminal_quests' => $terminal,
            ],
        ];
    }

    public function sync(User $user): void
    {
        $result = $this->calculate($user);

        UserTrustMetric::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'client_trust_score' => $result['score'],
                'last_recomputed_at' => now(),
            ]
        );
    }
}
