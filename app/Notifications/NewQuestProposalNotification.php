<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewQuestProposalNotification extends Notification
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
        $this->offer->loadMissing(['quest', 'freelancer']);
        $quest = $this->offer->quest;
        $url = route('quests.proposals.show', [$quest, $this->offer], absolute: true);
        $first = $notifiable->first_name ?: $notifiable->name;
        $fl = $this->offer->freelancer?->first_name ?: $this->offer->freelancer?->name ?: __('A freelancer');

        return (new MailMessage)
            ->subject(__('New proposal on :title', ['title' => $quest?->title ?? 'your quest']))
            ->markdown('mail.quests.proposal-received', [
                'firstName' => $first,
                'freelancerName' => $fl,
                'questTitle' => $quest?->title,
                'ctaUrl' => $url,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;
        $headline = __('New proposal received');

        return [
            'headline' => $headline,
            'title' => $headline,
            'quest_title' => $quest?->title,
            'body' => __(':name sent a proposal.', ['name' => $this->offer->freelancer?->name ?? 'Freelancer']),
            'preview' => Str::limit((string) $this->offer->pitch, 160),
            'href' => route('quests.proposals.show', [$quest, $this->offer], absolute: false),
            'kind' => 'quest_proposal_received',
        ];
    }
}
