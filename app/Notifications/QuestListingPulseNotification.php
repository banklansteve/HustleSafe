<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuestListingPulseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Quest $quest,
        public int $uniqueViewerCount,
    ) {}

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
            'kind' => 'quest_listing_pulse',
            'headline' => __('Your quest is picking up steam'),
            'title' => __('Your quest is picking up steam'),
            'quest_title' => $this->quest->title,
            'body' => __(':n unique freelancers have opened your brief — momentum matters.', ['n' => $this->uniqueViewerCount]),
            'href' => route('quests.show', $this->quest, absolute: false),
            'viewer_count' => $this->uniqueViewerCount,
        ];
    }
}
