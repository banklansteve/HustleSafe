<?php

namespace App\Notifications;

use App\Models\QuestBoost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestBoostGrantedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestBoost $boost,
        public bool $purchasedByClient = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $first = $notifiable->first_name ?: $notifiable->name;
        $url = route('quests.show', $this->boost->quest_id, absolute: true);

        return (new MailMessage)
            ->subject(__('Quest boosted: :title', ['title' => $this->boost->quest_title_snapshot]))
            ->markdown('mail.quests.boost-confirmed', [
                'firstName' => $first,
                'questTitle' => $this->boost->quest_title_snapshot,
                'tierLabel' => $this->boost->tierEnum()->label(),
                'expiresAt' => $this->boost->ends_at?->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                'purchasedByClient' => $this->purchasedByClient,
                'ctaUrl' => $url,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $body = $this->purchasedByClient
            ? __('Payment confirmed — ":title" is boosted for :duration. Your quest now ranks higher for matching freelancers.', [
                'title' => $this->boost->quest_title_snapshot,
                'duration' => $this->boost->tierEnum()->label(),
            ])
            : __(':title has been boosted for the next :duration by the platform. Your quest will appear at the top of search results.', [
                'title' => $this->boost->quest_title_snapshot,
                'duration' => $this->boost->tierEnum()->label(),
            ]);

        return [
            'kind' => 'quest_boost_granted',
            'headline' => __('Quest boosted'),
            'title' => __('Quest boosted'),
            'body' => $body,
            'href' => route('quests.show', $this->boost->quest_id, absolute: false),
            'starts_at' => $this->boost->starts_at?->toIso8601String(),
            'ends_at' => $this->boost->ends_at?->toIso8601String(),
        ];
    }
}
