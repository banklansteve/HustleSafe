<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProposalViewedMilestoneNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestOffer $offer,
        public int $viewCount,
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
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;

        return [
            'kind' => 'proposal_viewed_milestone',
            'headline' => __('Your proposal is getting attention'),
            'title' => __('Your proposal is getting attention'),
            'quest_title' => $quest?->title,
            'body' => __('The client has opened your proposal :n times — keep your thread polished and responsive.', ['n' => $this->viewCount]),
            'href' => $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: false) : '/',
            'view_count' => $this->viewCount,
        ];
    }
}
