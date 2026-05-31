<?php

namespace App\Notifications;

use App\Models\ConversationThreadReview;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConversationMonitoringSuperAdminEscalationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ConversationThreadReview $review,
        public readonly User $escalatedBy,
        public readonly string $note,
    ) {}

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
            'kind' => 'conversation_monitoring_super_admin_escalation',
            'title' => __('Conversation case escalated — action required'),
            'body' => __(':staff escalated a flagged conversation on “:quest”. :note', [
                'staff' => $this->escalatedBy->name,
                'quest' => $this->review->quest?->title ?? 'a quest',
                'note' => str($this->note)->limit(120),
            ]),
            'href' => route('admin.conversation-monitoring.index', ['review' => $this->review->id], absolute: false),
            'review_id' => $this->review->id,
        ];
    }
}
