<?php

namespace App\Notifications;

use App\Models\QuestBoost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestBoostAdminGrantedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestBoost $boost,
        public string $adminNote = '',
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
            ->subject(__('Your quest has been boosted'))
            ->line(__('Your quest ":title" has been boosted by our team.', ['title' => $this->boost->quest_title_snapshot]))
            ->when($this->adminNote !== '', fn (MailMessage $m) => $m->line($this->adminNote))
            ->line(__('Boost tier: :tier', ['tier' => $this->boost->tierEnum()->label()]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'quest_boost_admin_granted',
            'headline' => __('Quest boosted'),
            'title' => __('Your quest has been boosted'),
            'body' => $this->adminNote !== '' ? $this->adminNote : __('Boost is now active.'),
        ];
    }
}
