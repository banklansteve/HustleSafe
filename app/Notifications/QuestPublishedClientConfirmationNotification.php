<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestPublishedClientConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Quest $quest,
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
        $this->quest->loadMissing(['questCategory:id,name']);
        $first = $notifiable->first_name ?: $notifiable->name;
        $url = route('quests.show', $this->quest, absolute: true);

        return (new MailMessage)
            ->subject(__('Quest published: :title', ['title' => $this->quest->title]))
            ->markdown('mail.quests.published-client-confirmation', [
                'firstName' => $first,
                'questTitle' => $this->quest->title,
                'categoryName' => $this->quest->questCategory?->name,
                'ctaUrl' => $url,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $href = route('quests.show', $this->quest, absolute: false);

        return [
            'headline' => __('Quest published'),
            'title' => __('Your quest is live'),
            'body' => __('We emailed you a confirmation and alerted matching freelancers.'),
            'href' => $href,
            'kind' => 'quest_published_client',
            'quest_id' => $this->quest->id,
            'quest_title' => $this->quest->title,
        ];
    }
}
