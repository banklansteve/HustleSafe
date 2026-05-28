<?php

namespace App\Services\ReviewModeration;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\ReviewAmendmentRequest;
use App\Models\User;
use App\Notifications\ReviewAmendmentRequestedNotification;
use Illuminate\Support\Facades\DB;

class ReviewAmendmentService
{
    public function __construct(
        private readonly ReviewModerationActionLogger $logger,
        private readonly ReviewModerationSettingsService $settings,
        private readonly ReviewModerationPipelineService $pipeline,
    ) {}

    public function issue(Review $review, User $staff, string $instructions, array $requiredChanges = []): ReviewAmendmentRequest
    {
        return DB::transaction(function () use ($review, $staff, $instructions, $requiredChanges): ReviewAmendmentRequest {
            $hours = (int) config('review_moderation.amendment.hours_to_respond', 48);

            ReviewAmendmentRequest::query()
                ->where('review_id', $review->id)
                ->where('status', 'open')
                ->update(['status' => 'superseded']);

            $request = ReviewAmendmentRequest::query()->create([
                'review_id' => $review->id,
                'issued_by' => $staff->id,
                'instructions' => $instructions,
                'required_changes' => $requiredChanges,
                'expires_at' => now()->addHours($hours),
                'status' => 'open',
            ]);

            $review->forceFill(['status' => ReviewStatus::AmendmentPending])->save();

            $this->logger->log($review, $staff, 'amendment_requested', $instructions, [
                'amendment_request_id' => $request->id,
                'required_changes' => $requiredChanges,
            ]);

            $review->reviewer?->notify(new ReviewAmendmentRequestedNotification($review, $request));

            return $request;
        });
    }

    public function respond(Review $review, User $reviewer): Review
    {
        $open = ReviewAmendmentRequest::query()
            ->where('review_id', $review->id)
            ->where('status', 'open')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($open === null) {
            return $review;
        }

        return DB::transaction(function () use ($review, $reviewer, $open): Review {
            $open->forceFill([
                'status' => 'responded',
                'responded_at' => now(),
            ])->save();

            $review->forceFill(['status' => ReviewStatus::PendingReview])->save();

            $this->logger->log($review, $reviewer, 'amendment_responded', null, [
                'amendment_request_id' => $open->id,
            ]);

            return $review;
        });
    }

    public function expireOpenRequests(): int
    {
        $expired = ReviewAmendmentRequest::query()
            ->where('status', 'open')
            ->where('expires_at', '<=', now())
            ->with('review.authenticitySignals')
            ->get();

        $count = 0;
        foreach ($expired as $request) {
            $review = $request->review;
            if ($review === null) {
                continue;
            }

            $flagType = $review->authenticitySignals->first()?->signal_type ?? 'sentiment_mismatch';
            $action = $this->settings->defaultActionFor($flagType);
            $request->forceFill([
                'status' => 'expired',
                'default_action' => $action,
            ])->save();

            if ($action === 'auto_publish') {
                $this->pipeline->publish($review, null, 'amendment_expired_auto_publish');
            } else {
                $this->pipeline->remove($review, null, 'amendment_expired_auto_remove');
            }

            $this->logger->log($review, null, 'amendment_expired_default_action', null, [
                'amendment_request_id' => $request->id,
                'flag_type' => $flagType,
                'action' => $action,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingPromptsFor(User $user): array
    {
        return ReviewAmendmentRequest::query()
            ->where('status', 'open')
            ->where('expires_at', '>', now())
            ->whereHas('review', fn ($q) => $q->where('reviewer_id', $user->id)->where('status', ReviewStatus::AmendmentPending))
            ->with(['review.quest:id,title', 'review:id,quest_id,title,comment,rating'])
            ->latest()
            ->get()
            ->map(fn (ReviewAmendmentRequest $req) => [
                'request_id' => $req->id,
                'review_id' => $req->review_id,
                'quest_title' => $req->review?->quest?->title,
                'instructions' => $req->instructions,
                'required_changes' => $req->required_changes ?? [],
                'expires_at' => $req->expires_at?->toIso8601String(),
            ])
            ->all();
    }
}
