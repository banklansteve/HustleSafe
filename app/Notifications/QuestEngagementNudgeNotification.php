<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestEngagementNudgeNotification extends Notification
{
    use Queueable;

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
        $first = $notifiable->first_name ?: $notifiable->name;
        $message = (new MailMessage)
            ->subject($this->subjectLine)
            ->greeting(__('Hi :name,', ['name' => $first]));

        foreach ($this->bodyLines as $line) {
            $message->line($line);
        }

        return $message
            ->action($this->primaryLabel, $this->primaryUrl)
            ->line(__('This is an automated reminder from HustleSafe to keep your quest moving.'));
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
