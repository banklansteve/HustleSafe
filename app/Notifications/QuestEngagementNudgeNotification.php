<?php

namespace App\Notifications;

use App\Models\Quest;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestEngagementNudgeNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    /**
     * @param  list<string>  $bodyLines
     */
    public function __construct(
        public Quest $quest,
        public string $nudgeType,
        public string $subjectLine,
        public array $bodyLines,
        public string $primaryUrl,
        public string $primaryLabel,
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
        return $this->brandedMail(
            subject: $this->subjectLine,
            headline: $this->subjectLine,
            notifiable: $notifiable,
            lines: $this->bodyLines,
            ctaUrl: $this->primaryUrl,
            ctaLabel: $this->primaryLabel,
            footerLine: __('This is an automated reminder from HustleSafe to keep your quest moving.'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quest_nudge',
            'nudge_type' => $this->nudgeType,
            'quest_id' => $this->quest->id,
            'quest_title' => $this->quest->title,
            'subject' => $this->subjectLine,
            'url' => $this->primaryUrl,
        ];
    }
}
