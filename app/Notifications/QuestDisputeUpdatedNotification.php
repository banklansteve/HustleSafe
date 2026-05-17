<?php

namespace App\Notifications;

use App\Models\QuestDispute;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuestDisputeUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestDispute $dispute,
        public string $headline,
        public string $body,
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
        $this->dispute->loadMissing('quest');

        return [
            'kind' => 'quest_dispute_update',
            'headline' => $this->headline,
            'title' => $this->headline,
            'body' => $this->body,
            'dispute_uuid' => $this->dispute->uuid,
            'quest_title' => $this->dispute->quest?->title,
            'href' => route('disputes.show', $this->dispute, absolute: false),
        ];
    }
}
