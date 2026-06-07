<?php

namespace App\Notifications;

use App\Models\FreelancerSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreelancerProSuspendedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public FreelancerSubscription $subscription,
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
            ->subject(__('Your premium subscription has been suspended'))
            ->line(__('Your premium subscription has been suspended.'))
            ->line(__('Reason: :reason', ['reason' => $this->reason]))
            ->line(__('Your account has been reverted to the free tier proposal cap.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'freelancer_pro_suspended',
            'headline' => __('Premium suspended'),
            'title' => __('Premium subscription suspended'),
            'body' => __('Reason: :reason', ['reason' => $this->reason]),
        ];
    }
}
