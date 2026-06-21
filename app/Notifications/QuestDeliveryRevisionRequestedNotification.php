<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestDeliveryRevisionRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Quest $quest,
        public string $note,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('quests.proposals.show', [$this->quest, $this->quest->acceptedOffer], absolute: true);

        return (new MailMessage)
            ->subject(__('Revision requested — :title', ['title' => $this->quest->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? $notifiable->name]))
            ->line(__('The client requested revisions on your deliverable.'))
            ->line(__('Feedback: :note', ['note' => $this->note]))
            ->line(__('Resubmit from the proposal page when the updates are ready.'))
            ->action(__('View quest'), $url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quest_delivery_revision_requested',
            'quest_id' => $this->quest->id,
            'quest_title' => $this->quest->title,
            'url' => route('quests.proposals.show', [$this->quest, $this->quest->acceptedOffer]),
        ];
    }
}
