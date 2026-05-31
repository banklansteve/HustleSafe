<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalDeclinedFreelancerNotification extends Notification
{
    use Queueable, SendsBrandedMail;

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

        return $this->brandedMail(
            subject: __('Update on your proposal for :title', ['title' => $quest?->title ?? 'a quest']),
            headline: __('Proposal declined'),
            notifiable: $notifiable,
            lines: [
                __('The client declined this proposal. You remain free to pursue other quests on HustleSafe.'),
            ],
            ctaUrl: route('quests.explore', absolute: true),
            ctaLabel: __('Browse open quests'),
        );
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
