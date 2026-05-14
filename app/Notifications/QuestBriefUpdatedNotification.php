<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class QuestBriefUpdatedNotification extends Notification
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
        $quest = $this->quest->loadMissing(['client:id,first_name,name']);
        $url = route('quests.show', $quest, absolute: true);
        $clientName = $quest->client?->first_name ?: $quest->client?->name ?: __('The client');
        $first = $notifiable->first_name ?: $notifiable->name;

        return (new MailMessage)
            ->subject(__('Quest updated: :title', ['title' => $quest->title]))
            ->markdown('mail.quests.brief-updated', [
                'firstName' => $first,
                'clientName' => $clientName,
                'questTitle' => $quest->title,
                'reference' => $quest->reference_code ?? $quest->uuid,
                'ctaUrl' => $url,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $quest = $this->quest;

        $headline = __('Quest brief was updated');

        return [
            'headline' => $headline,
            'title' => $headline,
            'quest_title' => $quest->title,
            'body' => $quest->title,
            'preview' => Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $quest->description))), 160) ?: null,
            'href' => route('quests.show', $quest, absolute: false),
            'kind' => 'quest_brief_updated',
        ];
    }
}
