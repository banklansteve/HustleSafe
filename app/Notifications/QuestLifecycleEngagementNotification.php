<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestLifecycleEngagementNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $bodyLines
     */
    public function __construct(
        public Quest $quest,
        public string $emailKey,
        public string $subjectLine,
        public array $bodyLines,
        public string $primaryUrl,
        public string $primaryLabel,
        public string $secondaryUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $first = $notifiable->first_name ?: $notifiable->name;

        return (new MailMessage)
            ->subject($this->subjectLine)
            ->markdown('mail.quests.lifecycle-engagement', [
                'firstName' => $first,
                'questTitle' => $this->quest->title,
                'bodyLines' => $this->bodyLines,
                'primaryUrl' => $this->primaryUrl,
                'primaryLabel' => $this->primaryLabel,
                'secondaryUrl' => $this->secondaryUrl,
                'workflowDocUrl' => asset('docs/dispute-workflow.md'),
            ]);
    }
}
