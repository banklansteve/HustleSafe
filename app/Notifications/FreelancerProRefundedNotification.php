<?php

namespace App\Notifications;

use App\Support\NgnMoney;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreelancerProRefundedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $amountMinor,
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
            ->subject(__('Your premium charge has been refunded'))
            ->line(__('Your premium charge has been refunded (:amount) due to: :reason', [
                'amount' => NgnMoney::format($this->amountMinor),
                'reason' => $this->reason,
            ]))
            ->line(__('The amount has been credited to your wallet.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'freelancer_pro_refunded',
            'headline' => __('Premium refunded'),
            'title' => __('Premium charge refunded'),
            'body' => __(':amount credited to your wallet. Reason: :reason', [
                'amount' => NgnMoney::format($this->amountMinor),
                'reason' => $this->reason,
            ]),
        ];
    }
}
