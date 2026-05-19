<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AdminQuestModerationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Quest $quest,
        public string $title,
        public string $body,
        public string $kind = 'quest_moderation_update',
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
        return (new MailMessage)
            ->subject($this->title)
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?: $notifiable->name ?: __('there')]))
            ->line($this->body)
            ->line(__('Quest: :title', ['title' => $this->quest->title]))
            ->action(__('Open Quest'), route('quests.show', $this->quest, absolute: true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'headline' => $this->title,
            'title' => $this->title,
            'body' => Str::limit($this->body, 240),
            'quest_title' => $this->quest->title,
            'quest_reference' => $this->quest->reference_code ?? $this->quest->uuid,
            'href' => route('quests.show', $this->quest, absolute: false),
            'kind' => $this->kind,
        ];
    }
}
