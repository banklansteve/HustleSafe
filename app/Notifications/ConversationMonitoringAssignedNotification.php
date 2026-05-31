<?php

namespace App\Notifications;

use App\Models\ConversationThreadReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConversationMonitoringAssignedNotification extends Notification
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
            'kind' => 'conversation_monitoring_assigned',
            'title' => __('Conversation flag assigned to you'),
            'body' => __('Review flagged messages on “:quest”.', ['quest' => $this->review->quest?->title ?? 'a quest']),
            'href' => route('operations.conversation-monitoring.index', ['review' => $this->review->id], absolute: false),
            'review_id' => $this->review->id,
        ];
    }
}
