<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuestBoostUpsellNotification extends Notification
{
    use Queueable;

    public function __construct(public Quest $quest) {}

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
        $href = route('quests.show', $this->quest, absolute: false).'#boost-quest';

        return [
            'kind' => 'quest_boost_upsell',
            'headline' => __('Boost your quest'),
            'title' => __('Get more visibility'),
            'body' => __('Boost ":title" so matching pros see it sooner in search and Explore.', [
                'title' => $this->quest->title,
            ]),
            'href' => $href,
            'quest_id' => $this->quest->id,
            'dismissible' => true,
        ];
    }
}
