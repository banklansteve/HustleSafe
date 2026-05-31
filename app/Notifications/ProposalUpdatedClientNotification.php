<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalUpdatedClientNotification extends Notification
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
            subject: __('Proposal updated on :title', ['title' => $quest?->title ?? 'your quest']),
            headline: __('Proposal updated'),
            notifiable: $notifiable,
            lines: [
                __(':who revised their proposal — review the latest numbers and wording.', [
                    'who' => $this->offer->freelancer?->name ?? __('Freelancer'),
                ]),
            ],
            ctaUrl: $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: true) : url('/'),
            ctaLabel: __('Open proposal'),
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
            'kind' => 'proposal_updated_client',
            'headline' => __('Proposal updated'),
            'title' => __('Proposal updated'),
            'quest_title' => $quest?->title,
            'body' => __('A freelancer revised their proposal.'),
            'href' => $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: false) : '/',
        ];
    }
}
