<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;
        $first = $notifiable->first_name ?: $notifiable->name;

        return (new MailMessage)
            ->subject(__('You were shortlisted on :title', ['title' => $quest?->title ?? 'a quest']))
            ->line(__('Hi :name,', ['name' => $first]))
            ->line(__('You\'ve been shortlisted — the client may reach out soon. Keep an eye on clarifications and messages for this quest.'))
            ->action(__('View proposal'), $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: true) : url('/'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;

        return [
            'kind' => 'proposal_shortlisted_freelancer',
            'headline' => __('Shortlisted — nice one'),
            'title' => __('Shortlisted — nice one'),
            'quest_title' => $quest?->title,
            'body' => __('You\'ve been shortlisted — the client may reach out soon.'),
            'href' => $quest ? route('quests.proposals.show', [$quest, $this->offer], absolute: false) : '/',
        ];
    }
}
