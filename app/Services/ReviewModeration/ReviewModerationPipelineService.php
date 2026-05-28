<?php

namespace App\Services\ReviewModeration;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Services\RatingAggregationService;
use App\Services\TrustScoreOrchestrator;

class ReviewModerationPipelineService
{
    public function __construct(
        private readonly ReviewAuthenticityEngine $authenticity,
        private readonly ReviewModerationActionLogger $logger,
        private readonly TrustScoreOrchestrator $trustScores,
        private readonly RatingAggregationService $ratings,
    ) {}

    public function runAuthenticityScan(Review $review, ?string $clientIp): Review
    {
        $review = $this->authenticity->process($review, $clientIp);

        if ($this->authenticity->shouldHoldInQueue($review->fresh())) {
            $review->forceFill(['status' => ReviewStatus::PendingReview])->save();
            $this->logger->log($review, null, 'review_held_for_moderation');

            return $review;
        }

        return $this->publish($review, null, 'auto_publish_clean_scan');
    }

    public function publish(Review $review, ?\App\Models\User $actor, string $action): Review
    {
        $review->forceFill(['status' => ReviewStatus::Published])->save();
        $this->logger->log($review, $actor, $action);
        $this->ratings->syncForUser($review->reviewee);
        $this->trustScores->recalculate($review->reviewee);

        return $review->fresh();
    }

    public function remove(Review $review, ?\App\Models\User $actor, string $action, ?string $note = null): Review
    {
        $review->forceFill(['status' => ReviewStatus::Removed])->save();
        $this->logger->log($review, $actor, $action, $note);
        $this->ratings->syncForUser($review->reviewee);
        $this->trustScores->recalculate($review->reviewee);

        return $review->fresh();
    }
}
