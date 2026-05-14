<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalWithdrawnClientNotification extends Notification
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
        $this->offer->loadMissing('quest', 'freelancer');
        $quest = $this->offer->quest;
        $first = $notifiable->first_name ?: $notifiable->name;

        return (new MailMessage)
            ->subject(__('A freelancer withdrew their proposal'))
            ->line(__('Hi :name,', ['name' => $first]))
            ->line(__(':who withdrew their proposal on “:title”.', [
                'who' => $this->offer->freelancer?->name ?? __('A freelancer'),
                'title' => $quest?->title ?? '',
            ]))
            ->action(__('Open quest'), $quest ? route('quests.show', $quest, absolute: true) : url('/'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;

        return [
            'kind' => 'proposal_withdrawn_client',
            'headline' => __('Proposal withdrawn'),
            'title' => __('Proposal withdrawn'),
            'quest_title' => $quest?->title,
            'body' => __('A freelancer withdrew their proposal.'),
            'href' => $quest ? route('quests.show', $quest, absolute: false) : '/',
        ];
    }
}
