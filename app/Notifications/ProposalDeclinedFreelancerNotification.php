<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalDeclinedFreelancerNotification extends Notification
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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;
        $first = $notifiable->first_name ?: $notifiable->name;

        return (new MailMessage)
            ->subject(__('Update on your proposal for :title', ['title' => $quest?->title ?? 'a quest']))
            ->line(__('Hi :name,', ['name' => $first]))
            ->line(__('The client declined this proposal. You remain free to pursue other quests on HustleSafe.'))
            ->action(__('Browse open quests'), url('/quests/explore'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;

        return [
            'kind' => 'proposal_declined_freelancer',
            'headline' => __('Proposal declined'),
            'title' => __('Proposal declined'),
            'quest_title' => $quest?->title,
            'body' => __('The client declined your proposal.'),
            'href' => $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: false) : '/',
        ];
    }
}
