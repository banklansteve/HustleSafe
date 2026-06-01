<?php

namespace App\Notifications;

use App\Models\QuestBoost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuestBoostGrantedNotification extends Notification
{
    use Queueable;

    public function __construct(public QuestBoost $boost) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'quest_boost_granted',
            'headline' => __('Quest boosted'),
            'title' => __('Quest boosted'),
            'body' => __(':title has been boosted for the next :duration by the platform. Your quest will appear at the top of search results.', [
                'title' => $this->boost->quest_title_snapshot,
                'duration' => $this->boost->tierEnum()->label(),
            ]),
            'href' => route('quests.show', $this->boost->quest_id, absolute: false),
            'starts_at' => $this->boost->starts_at?->toIso8601String(),
            'ends_at' => $this->boost->ends_at?->toIso8601String(),
        ];
    }
}
