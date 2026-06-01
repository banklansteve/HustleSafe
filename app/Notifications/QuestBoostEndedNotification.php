<?php

namespace App\Notifications;

use App\Models\QuestBoost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuestBoostEndedNotification extends Notification
{
    use Queueable;

    public function __construct(public QuestBoost $boost, public ?string $context = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'quest_boost_ended',
            'headline' => __('Quest boost ended'),
            'title' => __('Quest boost ended'),
            'body' => __(':title boost has ended. Your quest continues to be visible in standard search results.', [
                'title' => $this->boost->quest_title_snapshot,
            ]),
            'href' => route('quests.show', $this->boost->quest_id, absolute: false),
            'context' => $this->context,
            'ended_at' => ($this->boost->actual_ended_at ?? now())->toIso8601String(),
        ];
    }
}
