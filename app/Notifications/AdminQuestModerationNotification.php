<?php

namespace App\Notifications;

use App\Models\Quest;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminQuestModerationNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public Quest $quest,
        public string $title,
        public string $body,
        public string $kind,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            subject: $this->title,
            headline: $this->title,
            notifiable: $notifiable,
            lines: [$this->body],
            ctaUrl: route('quests.show', $this->quest, absolute: true),
            ctaLabel: __('View quest'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'quest_id' => $this->quest->id,
            'quest_title' => $this->quest->title,
            'href' => route('quests.show', $this->quest, absolute: false),
        ];
    }
}
