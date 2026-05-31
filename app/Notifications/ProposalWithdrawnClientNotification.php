<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalWithdrawnClientNotification extends Notification
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
        $this->offer->loadMissing('quest', 'freelancer');
        $quest = $this->offer->quest;

        return $this->brandedMail(
            subject: __('A freelancer withdrew their proposal'),
            headline: __('Proposal withdrawn'),
            notifiable: $notifiable,
            lines: [
                __(':who withdrew their proposal on “:title”.', [
                    'who' => $this->offer->freelancer?->name ?? __('A freelancer'),
                    'title' => $quest?->title ?? '',
                ]),
            ],
            ctaUrl: $quest ? route('quests.show', $quest, absolute: true) : url('/'),
            ctaLabel: __('Open quest'),
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
            'kind' => 'proposal_withdrawn_client',
            'headline' => __('Proposal withdrawn'),
            'title' => __('Proposal withdrawn'),
            'quest_title' => $quest?->title,
            'body' => __('A freelancer withdrew their proposal.'),
            'href' => $quest ? route('quests.show', $quest, absolute: false) : '/',
        ];
    }
}
