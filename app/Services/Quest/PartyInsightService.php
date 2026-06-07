<?php

namespace App\Services\Quest;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\User;
use App\Services\Verification\VerificationEngineService;

class PartyInsightService
{
    public function __construct(
        protected VerificationEngineService $verificationEngine,
    ) {}

    /**
     * Compact sponsor card for freelancers viewing a quest.
     *
     * @return array<string, mixed>
     */
    public function sponsorInsightForQuest(Quest $quest): array
    {
        $client = $quest->client;
        if ($client === null) {
            return [];
        }

        $client->loadMissing(['trustMetrics', 'stateModel', 'role']);

        $posted90 = Quest::query()
            ->where('client_id', $client->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->count();

        $completedAsClient = Quest::query()
            ->where('client_id', $client->id)
            ->where('status', QuestStatus::Completed)
            ->count();

        $disputes90 = QuestDispute::query()
            ->whereHas('quest', fn ($q) => $q->where('client_id', $client->id))
            ->where('created_at', '>=', now()->subDays(90))
            ->count();

        $fundedQuests = Quest::query()
            ->where('client_id', $client->id)
            ->whereNotNull('escrow_funded_at')
            ->count();

        $paidOutQuests = Quest::query()
            ->where('client_id', $client->id)
            ->where('status', QuestStatus::Completed)
            ->whereNotNull('funds_released_at')
            ->count();

        $paymentRate = $fundedQuests > 0
            ? (int) round(($paidOutQuests / $fundedQuests) * 100)
            : null;

        $rating = $client->trustMetrics?->avg_rating_as_client;
        $ratingCount = (int) ($client->trustMetrics?->ratings_count_as_client ?? 0);

        $highlights = $this->sponsorHighlights(
            $posted90,
            $completedAsClient,
            $disputes90,
            $paymentRate,
            $rating,
            $ratingCount,
        );

        return [
            'party' => 'client',
            'name' => $client->name,
            'first_name' => $client->first_name,
            'avatar_url' => $client->avatar_url,
            'slug' => $client->slug,
            'location' => $client->stateModel?->name,
            'rating' => $rating !== null ? round((float) $rating, 1) : null,
            'rating_count' => $ratingCount,
            'quests_posted_90_days' => $posted90,
            'quests_completed' => $completedAsClient,
            'payment_rate_percent' => $paymentRate,
            'disputes_90_days' => $disputes90,
            'dispute_free_90_days' => $disputes90 === 0,
            'trust_score' => (int) ($client->trustMetrics?->client_trust_score ?? 0),
            'profile_route' => null,
            'highlights' => $highlights,
        ];
    }

    /**
     * Compact freelancer card for clients viewing a proposal.
     *
     * @return array<string, mixed>
     */
    public function freelancerInsight(User $freelancer): array
    {
        $freelancer->loadMissing(['trustMetrics', 'stateModel', 'localGovernmentModel', 'role']);

        $completedYear = Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->where('status', QuestStatus::Completed)
            ->where('completed_at', '>=', now()->subYear())
            ->count();

        $completed30 = Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->where('status', QuestStatus::Completed)
            ->where('completed_at', '>=', now()->subDays(30))
            ->count();

        $assignedTotal = Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->whereIn('status', [
                QuestStatus::Completed,
                QuestStatus::CancelledMutual,
                QuestStatus::CancelledByAdmin,
                QuestStatus::WithdrawnByFreelancer,
            ])
            ->count();

        $completedTotal = Quest::query()
            ->where('freelancer_id', $freelancer->id)
            ->where('status', QuestStatus::Completed)
            ->count();

        $completionRate = $assignedTotal > 0
            ? (int) round(($completedTotal / $assignedTotal) * 100)
            : null;

        $disputes90 = QuestDispute::query()
            ->whereHas('quest', fn ($q) => $q->where('freelancer_id', $freelancer->id))
            ->where('created_at', '>=', now()->subDays(90))
            ->count();

        $level = $this->verificationEngine->effectiveLevel($freelancer);
        $rating = $freelancer->trustMetrics?->avg_rating_as_freelancer;
        $ratingCount = (int) ($freelancer->trustMetrics?->ratings_count_as_freelancer ?? 0);

        $highlights = $this->freelancerHighlights(
            $completed30,
            $completedYear,
            $completionRate,
            $disputes90,
            $rating,
            $ratingCount,
        );

        return [
            'party' => 'freelancer',
            'name' => $freelancer->name,
            'first_name' => $freelancer->first_name,
            'avatar_url' => $freelancer->avatar_url,
            'slug' => $freelancer->slug,
            'location' => trim(implode(', ', array_filter([
                $freelancer->localGovernmentModel?->name,
                $freelancer->stateModel?->name,
            ]))),
            'verification_level' => $level,
            'tier_label' => $this->freelancerTierLabel($level, $freelancer),
            'rating' => $rating !== null ? round((float) $rating, 1) : null,
            'rating_count' => $ratingCount,
            'jobs_completed_year' => $completedYear,
            'jobs_completed_30_days' => $completed30,
            'completion_rate_percent' => $completionRate,
            'disputes_90_days' => $disputes90,
            'dispute_free_90_days' => $disputes90 === 0,
            'trust_score' => (int) ($freelancer->trustMetrics?->freelancer_trust_score ?? 0),
            'profile_route' => $freelancer->slug ? 'freelancers.public' : null,
            'highlights' => $highlights,
        ];
    }

    protected function freelancerTierLabel(int $level, User $freelancer): ?string
    {
        if ($level < 1) {
            return null;
        }

        $base = $this->verificationEngine->levelLabel($level, $freelancer);

        if ($level >= 5) {
            return __('Tier :level — Trusted contributor', ['level' => $level]).' · '.$base;
        }

        return __('Tier :level', ['level' => $level]).' · '.$base;
    }

    /**
     * @return list<string>
     */
    protected function sponsorHighlights(
        int $posted90,
        int $completed,
        int $disputes90,
        ?int $paymentRate,
        mixed $rating,
        int $ratingCount,
    ): array {
        $lines = [];

        if ($rating !== null && $ratingCount > 0) {
            $lines[] = __('★ :rating (:count reviews)', [
                'rating' => round((float) $rating, 1),
                'count' => $ratingCount,
            ]);
        }

        if ($posted90 > 0) {
            $lines[] = __('Posted :count quests in the last 90 days', ['count' => $posted90]);
        }

        if ($paymentRate !== null && $paymentRate >= 0) {
            $lines[] = __('Payment rate: :rate%', ['rate' => $paymentRate]);
        }

        if ($completed > 0) {
            $lines[] = __(':count completed quests on HustleSafe', ['count' => $completed]);
        }

        if ($disputes90 === 0) {
            $lines[] = __('No disputes in the last 90 days');
        } elseif ($disputes90 > 0) {
            $lines[] = __(':count dispute(s) in the last 90 days', ['count' => $disputes90]);
        }

        if ($lines === []) {
            $lines[] = __('New client — limited history on HustleSafe yet');
        }

        return array_slice($lines, 0, 5);
    }

    /**
     * @return list<string>
     */
    protected function freelancerHighlights(
        int $completed30,
        int $completedYear,
        ?int $completionRate,
        int $disputes90,
        mixed $rating,
        int $ratingCount,
    ): array {
        $lines = [];

        if ($rating !== null && $ratingCount > 0) {
            $lines[] = __('★ :rating (:count reviews)', [
                'rating' => round((float) $rating, 1),
                'count' => $ratingCount,
            ]);
        }

        if ($completed30 > 0) {
            $lines[] = __('Completed :count jobs in the last 30 days', ['count' => $completed30]);
        }

        if ($completedYear > 0) {
            $lines[] = __('Completed :count jobs in the last year', ['count' => $completedYear]);
        }

        if ($completionRate !== null) {
            $lines[] = __(':rate% completion rate', ['rate' => $completionRate]);
        }

        if ($disputes90 === 0) {
            $lines[] = __('No disputes in the last 90 days');
        } elseif ($disputes90 > 0) {
            $lines[] = __(':count dispute(s) in the last 90 days', ['count' => $disputes90]);
        }

        if ($lines === []) {
            $lines[] = __('Building track record on HustleSafe');
        }

        return array_slice($lines, 0, 5);
    }
}
