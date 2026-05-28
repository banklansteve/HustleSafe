<?php

namespace App\Services\ReviewModeration;

use App\Models\Review;
use App\Models\ReviewModerationActionLog;
use App\Models\User;

class ReviewModerationActionLogger
{
    public function log(
        ?Review $review,
        ?User $actor,
        string $action,
        ?string $note = null,
        array $payload = [],
    ): ReviewModerationActionLog {
        return ReviewModerationActionLog::query()->create([
            'review_id' => $review?->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'note' => $note,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
