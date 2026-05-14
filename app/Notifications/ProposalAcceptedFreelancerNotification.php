<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalAcceptedFreelancerNotification extends Notification
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
        $this->offer->loadMissing(['quest']);
        $quest = $this->offer->quest;
        $first = $notifiable->first_name ?: $notifiable->name;

        return (new MailMessage)
            ->subject(__('Your proposal was accepted — escrow next'))
            ->markdown('mail.quests.proposal-accepted-freelancer', [
                'firstName' => $first,
                'questTitle' => $quest?->title,
                'proposalUrl' => $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: true) : url('/'),
                'termsUrl' => route('legal.terms', absolute: true),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;

        return [
            'kind' => 'proposal_accepted_freelancer',
            'headline' => __('Your proposal was accepted'),
            'title' => __('Your proposal was accepted'),
            'quest_title' => $quest?->title,
            'body' => __('Wait for escrow funding before starting billed work. Payouts follow client completion confirmation.'),
            'href' => $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: false) : '/',
        ];
    }
}
