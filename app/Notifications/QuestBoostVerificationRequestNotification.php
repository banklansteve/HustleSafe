<?php

namespace App\Notifications;

use App\Models\QuestBoost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestBoostVerificationRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestBoost $boost,
        public string $message,
        public int $responseHours,
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
            ->subject(__('Additional context needed for your boosted quest'))
            ->line(__('Your quest ":title" has been flagged for review.', ['title' => $this->boost->quest_title_snapshot]))
            ->line($this->message !== '' ? $this->message : __('Please provide additional context about this quest.'))
            ->line(__('You have :hours hours to respond before the boost may be removed.', ['hours' => $this->responseHours]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'quest_boost_verification_request',
            'headline' => __('Boost review'),
            'title' => __('Context needed for boosted quest'),
            'body' => $this->message !== '' ? $this->message : __('Please respond within :hours hours.', ['hours' => $this->responseHours]),
        ];
    }
}
