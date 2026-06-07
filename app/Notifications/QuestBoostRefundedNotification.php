<?php

namespace App\Notifications;

use App\Models\QuestBoost;
use App\Support\NgnMoney;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestBoostRefundedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestBoost $boost,
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
            ->subject(__('Your quest boost fee has been refunded'))
            ->line(__(':amount has been refunded for your quest boost on ":title".', [
                'amount' => NgnMoney::format($this->amountMinor),
                'title' => $this->boost->quest_title_snapshot,
            ]))
            ->line(__('Reason: :reason', ['reason' => $this->reason]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'quest_boost_refunded',
            'headline' => __('Boost refunded'),
            'title' => __('Quest boost fee refunded'),
            'body' => __(':amount credited. Reason: :reason', [
                'amount' => NgnMoney::format($this->amountMinor),
                'reason' => $this->reason,
            ]),
        ];
    }
}
