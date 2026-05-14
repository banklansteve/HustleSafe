<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalUpdatedClientNotification extends Notification
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
            ->subject(__('Proposal updated on :title', ['title' => $quest?->title ?? 'your quest']))
            ->line(__('Hi :name,', ['name' => $first]))
            ->line(__(':who revised their proposal — review the latest numbers and wording.', ['who' => $this->offer->freelancer?->name ?? __('Freelancer')]))
            ->action(__('Open proposal'), $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: true) : url('/'));
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
