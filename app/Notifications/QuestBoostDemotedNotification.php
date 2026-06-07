<?php

namespace App\Notifications;

use App\Models\QuestBoost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestBoostDemotedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestBoost $boost,
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
            ->subject(__('Your quest boost was removed'))
            ->line(__('Your quest boost for ":title" was removed.', ['title' => $this->boost->quest_title_snapshot]))
            ->line(__('Reason: :reason', ['reason' => $this->reason]))
            ->line(__('Any eligible refund has been credited to your wallet.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'quest_boost_demoted',
            'headline' => __('Boost removed'),
            'title' => __('Quest boost removed'),
            'body' => __('Reason: :reason', ['reason' => $this->reason]),
        ];
    }
}
