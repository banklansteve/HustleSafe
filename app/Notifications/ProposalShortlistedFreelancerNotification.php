<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProposalShortlistedFreelancerNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestOffer $offer,
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
        $title = $quest?->title ?? __('a quest');

        return [
            'kind' => 'proposal_shortlisted_freelancer',
            'headline' => __('Shortlisted'),
            'title' => __('Shortlisted'),
            'quest_title' => $quest?->title,
            'body' => __('Your proposal has been shortlisted for :title. The client may reach out with questions.', ['title' => $title]),
            'line' => __('Your proposal has been shortlisted for :title. The client may reach out with questions.', ['title' => $title]),
            'href' => $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: false) : '/',
        ];
    }
}
