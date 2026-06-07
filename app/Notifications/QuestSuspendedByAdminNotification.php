<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestSuspendedByAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Quest $quest,
        public string $reason,
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
            ->subject(__('Your quest has been suspended'))
            ->line(__('Your quest ":title" has been suspended by an administrator.', ['title' => $this->quest->title]))
            ->line(__('Reason: :reason', ['reason' => $this->reason]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'quest_suspended_admin',
            'headline' => __('Quest suspended'),
            'title' => __('Quest suspended by admin'),
            'body' => __('Reason: :reason', ['reason' => $this->reason]),
        ];
    }
}
