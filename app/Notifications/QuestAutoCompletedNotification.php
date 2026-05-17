<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestAutoCompletedNotification extends Notification
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
        $first = $notifiable->first_name ?: $notifiable->name;
        $quest = $this->quest;

        return (new MailMessage)
            ->subject(__('Quest marked complete: :title', ['title' => $quest->title]))
            ->markdown('mail.quests.auto-completed', [
                'firstName' => $first,
                'questTitle' => $quest->title,
                'questUrl' => route('quests.show', [$quest->getRouteKey()], absolute: true),
                'disputesUrl' => route('disputes.index', absolute: true),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $quest = $this->quest;

        return [
            'kind' => 'quest_auto_completed',
            'headline' => __('Quest auto-completed'),
            'title' => __('Quest auto-completed'),
            'quest_title' => $quest->title,
            'body' => __('This quest was marked complete automatically after the review window described in your emails and Terms.'),
            'href' => route('quests.show', [$quest->getRouteKey()], absolute: false),
        ];
    }
}
