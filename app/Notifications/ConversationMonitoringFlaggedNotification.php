<?php

namespace App\Notifications;

use App\Models\ConversationThreadReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConversationMonitoringFlaggedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ConversationThreadReview $review) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->review->loadMissing('quest');

        return [
            'kind' => 'conversation_monitoring_flagged',
            'title' => __('New flagged message on assigned case'),
            'body' => __('Review updated flags on “:quest”.', [
                'quest' => $this->review->quest?->title ?? 'a quest',
            ]),
            'href' => route('operations.conversation-monitoring.index', ['review' => $this->review->id], absolute: false),
            'review_id' => $this->review->id,
        ];
    }
}
