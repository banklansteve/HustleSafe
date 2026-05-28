<?php

namespace App\Services\ReviewModeration;

use App\Enums\ReviewAuthenticityFlag;
use App\Enums\ReviewStatus;
use App\Enums\ReviewType;
use App\Models\Quest;
use App\Models\Review;
use App\Models\ReviewAuthenticitySignal;
use App\Models\ReviewModerationCluster;
use App\Models\User;
use App\Support\ReviewSubnetHelper;

class ReviewAuthenticityEngine
{
    public function __construct(
        private readonly ReviewSentimentAnalyzer $sentiment,
        private readonly ReviewQualityScorer $quality,
        private readonly ReviewModerationActionLogger $logger,
    ) {}

    public function process(Review $review, ?string $clientIp): Review
    {
        $review->loadMissing(['quest', 'reviewer', 'reviewee']);

        $subnet = ReviewSubnetHelper::fromIp($clientIp);
        $sentiment = $this->sentiment->analyze($review->comment);
        $quality = $this->quality->score($review->title, $review->comment);

        $review->forceFill([
            'reviewer_subnet' => $subnet,
            'sentiment_score' => $sentiment['score'],
            'quality_score' => $quality['score'],
            'is_brief' => $quality['is_brief'],
        ])->save();

        ReviewAuthenticitySignal::query()->where('review_id', $review->id)->delete();

        $signals = [];
        $signals[] = $this->detectSentimentMismatch($review, $sentiment);
        $signals = array_merge($signals, $this->detectVelocityCluster($review));
        $signals = array_merge($signals, $this->detectReciprocalPair($review));
        $signals = array_merge($signals, $this->detectIpCluster($review, $subnet));

        $signals = array_values(array_filter($signals));

        $flag = $this->compositeFlag($signals);
        $requiresQueue = $flag !== ReviewAuthenticityFlag::Clean || $review->moderation_cluster_id !== null;

        $review->forceFill([
            'authenticity_flag' => $flag->value,
        ])->save();

        $this->logger->log($review, null, 'authenticity_scan_completed', null, [
            'flag' => $flag->value,
            'signal_count' => count($signals),
            'requires_queue' => $requiresQueue,
        ]);

        return $review->fresh();
    }

    public function shouldHoldInQueue(Review $review): bool
    {
        $flag = ReviewAuthenticityFlag::tryFrom((string) $review->authenticity_flag) ?? ReviewAuthenticityFlag::Clean;

        return $flag !== ReviewAuthenticityFlag::Clean
            || $review->moderation_cluster_id !== null
            || $review->authenticitySignals()->whereIn('signal_type', [
                'velocity_cluster',
                'reciprocal_pair',
                'ip_cluster',
                'sentiment_mismatch',
            ])->exists();
    }

    /**
     * @param  array<int, ReviewAuthenticitySignal|null>  $signals
     */
    private function compositeFlag(array $signals): ReviewAuthenticityFlag
    {
        $types = collect($signals)->filter()->pluck('signal_type')->unique();

        if ($types->contains(fn ($t) => in_array($t, ['velocity_cluster', 'ip_cluster', 'reciprocal_pair'], true))) {
            return ReviewAuthenticityFlag::HighRisk;
        }

        if ($types->contains('sentiment_mismatch')) {
            return ReviewAuthenticityFlag::Suspicious;
        }

        return ReviewAuthenticityFlag::Clean;
    }

    /**
     * @param  array{score: float, polarity: string}  $sentiment
     */
    private function detectSentimentMismatch(Review $review, array $sentiment): ?ReviewAuthenticitySignal
    {
        $rating = (int) ($review->rating ?? 0);
        if ($rating < 1) {
            return null;
        }

        $mismatch = ($sentiment['score'] > (float) config('review_moderation.authenticity.sentiment.positive_threshold', 0.6)
                && $rating <= 2)
            || ($sentiment['score'] < (float) config('review_moderation.authenticity.sentiment.negative_threshold', 0.3)
                && $rating >= 4);

        if (! $mismatch) {
            return null;
        }

        return ReviewAuthenticitySignal::query()->create([
            'review_id' => $review->id,
            'signal_type' => 'sentiment_mismatch',
            'label' => 'Potential coerced review',
            'metadata' => [
                'sentiment_score' => $sentiment['score'],
                'polarity' => $sentiment['polarity'],
                'rating' => $rating,
            ],
            'confidence' => 0.85,
        ]);
    }

    /**
     * @return list<ReviewAuthenticitySignal>
     */
    private function detectVelocityCluster(Review $review): array
    {
        if ($review->rating !== 5 || $review->reviewer_party !== 'client') {
            return [];
        }

        $cfg = config('review_moderation.authenticity.velocity');
        $windowStart = now()->subHours((int) ($cfg['window_hours'] ?? 24));

        $candidates = Review::query()
            ->where('reviewee_id', $review->reviewee_id)
            ->where('rating', 5)
            ->where('status', '!=', ReviewStatus::Removed->value)
            ->where('created_at', '>=', $windowStart)
            ->with('reviewer:id,created_at')
            ->get();

        if ($candidates->count() < (int) ($cfg['min_five_star'] ?? 3)) {
            return [];
        }

        $youngReviewers = $candidates->filter(function (Review $r) use ($cfg): bool {
            $reviewer = $r->reviewer;
            if ($reviewer === null) {
                return true;
            }

            $accountYoung = $reviewer->created_at?->gte(now()->subDays((int) ($cfg['young_account_days'] ?? 30))) ?? true;
            $contractsLow = $this->completedContractCount($reviewer->id) < (int) ($cfg['min_contracts_trusted'] ?? 3);

            return $accountYoung || $contractsLow;
        });

        if ($youngReviewers->count() < (int) ($cfg['min_young_reviewers'] ?? 2)) {
            return [];
        }

        $cluster = ReviewModerationCluster::query()->firstOrCreate(
            [
                'cluster_type' => 'velocity',
                'primary_reviewee_id' => $review->reviewee_id,
                'status' => 'open',
            ],
            [
                'metadata' => [
                    'window_hours' => $cfg['window_hours'] ?? 24,
                    'detected_at' => now()->toIso8601String(),
                ],
            ],
        );

        $signals = [];
        foreach ($candidates as $candidate) {
            $candidate->forceFill(['moderation_cluster_id' => $cluster->id])->save();
            $signals[] = ReviewAuthenticitySignal::query()->create([
                'review_id' => $candidate->id,
                'signal_type' => 'velocity_cluster',
                'label' => 'Velocity anomaly cluster',
                'metadata' => ['cluster_id' => $cluster->id],
                'confidence' => 0.9,
            ]);
        }

        return $signals;
    }

    /**
     * @return list<ReviewAuthenticitySignal>
     */
    private function detectReciprocalPair(Review $review): array
    {
        $cfg = config('review_moderation.authenticity.reciprocal');
        $windowHours = (int) ($cfg['window_hours'] ?? 6);
        $closeDays = (int) ($cfg['contract_close_days'] ?? 7);
        $maxDelta = (int) ($cfg['max_star_delta'] ?? 1);

        $counterpart = Review::query()
            ->with('quest:id,completed_at,client_id,freelancer_id')
            ->where('reviewer_id', $review->reviewee_id)
            ->where('reviewee_id', $review->reviewer_id)
            ->where('id', '!=', $review->id)
            ->where('created_at', '>=', $review->created_at->copy()->subHours($windowHours))
            ->where('created_at', '<=', $review->created_at->copy()->addHours($windowHours))
            ->whereNotNull('rating')
            ->first();

        if ($counterpart === null) {
            return [];
        }

        $review->loadMissing('quest:id,completed_at');
        if (! $this->reciprocalContractsWithinWindow($review->quest, $counterpart->quest, $closeDays)) {
            return [];
        }

        if (abs((int) $review->rating - (int) $counterpart->rating) > $maxDelta) {
            return [];
        }

        $cluster = ReviewModerationCluster::query()->firstOrCreate(
            [
                'cluster_type' => 'reciprocal',
                'primary_reviewee_id' => null,
                'status' => 'open',
            ],
            [
                'metadata' => [
                    'pair' => [$review->id, $counterpart->id],
                    'detected_at' => now()->toIso8601String(),
                ],
            ],
        );

        $signals = [];
        foreach ([$review, $counterpart] as $member) {
            $member->forceFill(['moderation_cluster_id' => $cluster->id])->save();
            $signals[] = ReviewAuthenticitySignal::query()->create([
                'review_id' => $member->id,
                'signal_type' => 'reciprocal_pair',
                'label' => 'Reciprocal review pair',
                'metadata' => [
                    'cluster_id' => $cluster->id,
                    'counterpart_review_id' => $member->id === $review->id ? $counterpart->id : $review->id,
                ],
                'confidence' => 0.88,
            ]);
        }

        return $signals;
    }

    /**
     * @return list<ReviewAuthenticitySignal>
     */
    private function detectIpCluster(Review $review, ?string $subnet): array
    {
        if ($subnet === null) {
            return [];
        }

        $cfg = config('review_moderation.authenticity.ip_cluster');
        $windowStart = now()->subHours((int) ($cfg['window_hours'] ?? 72));

        $matches = Review::query()
            ->where('reviewee_id', $review->reviewee_id)
            ->where('reviewer_subnet', $subnet)
            ->where('created_at', '>=', $windowStart)
            ->where('status', '!=', ReviewStatus::Removed->value)
            ->get();

        if ($matches->count() < (int) ($cfg['min_reviews'] ?? 3)) {
            return [];
        }

        $cluster = ReviewModerationCluster::query()->firstOrCreate(
            [
                'cluster_type' => 'ip_subnet',
                'primary_reviewee_id' => $review->reviewee_id,
                'status' => 'open',
            ],
            [
                'metadata' => [
                    'subnet' => $subnet,
                    'window_hours' => $cfg['window_hours'] ?? 72,
                ],
            ],
        );

        $signals = [];
        foreach ($matches as $match) {
            $match->forceFill(['moderation_cluster_id' => $cluster->id])->save();
            $signals[] = ReviewAuthenticitySignal::query()->create([
                'review_id' => $match->id,
                'signal_type' => 'ip_cluster',
                'label' => 'Subnet collision cluster',
                'metadata' => [
                    'cluster_id' => $cluster->id,
                    'subnet' => $subnet,
                ],
                'confidence' => 0.82,
            ]);
        }

        return $signals;
    }

    private function reciprocalContractsWithinWindow(?Quest $a, ?Quest $b, int $days): bool
    {
        if ($a?->completed_at === null || $b?->completed_at === null) {
            return false;
        }

        return $a->completed_at->diffInDays($b->completed_at) <= $days;
    }

    private function completedContractCount(int $userId): int
    {
        return (int) Quest::query()
            ->where(function ($q) use ($userId): void {
                $q->where('client_id', $userId)->orWhere('freelancer_id', $userId);
            })
            ->whereNotNull('completed_at')
            ->count();
    }
}
