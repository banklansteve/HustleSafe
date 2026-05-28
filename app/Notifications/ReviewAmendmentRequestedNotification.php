<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\ReviewAmendmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReviewAmendmentRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Review $review,
        private readonly ReviewAmendmentRequest $request,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'review_amendment_required',
            'title' => __('Update your review'),
            'body' => $this->request->instructions,
            'review_id' => $this->review->id,
            'amendment_request_id' => $this->request->id,
            'required_changes' => $this->request->required_changes ?? [],
            'expires_at' => $this->request->expires_at?->toIso8601String(),
            'href' => route('account.show', ['amend_review' => $this->review->id]),
        ];
    }
}
