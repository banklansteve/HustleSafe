<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalEscrowFundedFreelancerNotification extends Notification
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
            ->subject(__('Escrow funded — you are cleared to start on :title', ['title' => $quest?->title ?? 'your quest']))
            ->markdown('mail.quests.proposal-escrow-funded-freelancer', [
                'firstName' => $first,
                'questTitle' => $quest?->title,
                'threadUrl' => $quest ? route('quests.messages.show', [$quest->getRouteKey()], absolute: true) : url('/'),
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
            'kind' => 'proposal_escrow_funded_freelancer',
            'headline' => __('Escrow funded — green light'),
            'title' => __('Escrow funded — green light'),
            'quest_title' => $quest?->title,
            'body' => __('The client confirmed escrow. You can start scheduled work.'),
            'href' => $quest ? route('quests.messages.show', [$quest->getRouteKey()], absolute: false) : '/',
        ];
    }
}
