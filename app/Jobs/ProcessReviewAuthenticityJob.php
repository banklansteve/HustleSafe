<?php

namespace App\Jobs;

use App\Models\Review;
use App\Services\ReviewModeration\ReviewModerationPipelineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessReviewAuthenticityJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $reviewId,
        public readonly ?string $clientIp = null,
    ) {}

    public function handle(ReviewModerationPipelineService $pipeline): void
    {
        $review = Review::query()->find($this->reviewId);
        if ($review === null) {
            return;
        }

        $pipeline->runAuthenticityScan($review, $this->clientIp);
    }
}
