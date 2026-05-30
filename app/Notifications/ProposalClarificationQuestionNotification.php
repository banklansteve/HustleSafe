<?php

namespace App\Notifications;

use App\Models\ProposalClarificationMessage;
use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalClarificationQuestionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestOffer $offer,
        public ProposalClarificationMessage $message,
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
            ->subject(__('Pre-award question on :title', ['title' => $quest?->title ?? 'a quest']))
            ->line(__('Hi :name,', ['name' => $first]))
            ->line(__('The client asked a clarifying question before deciding on your proposal:'))
            ->line('“'.str($this->message->body)->limit(400).'”')
            ->action(__('Answer in clarification thread'), route('quests.proposals.clarify', [$quest, $this->offer], absolute: true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;

        return [
            'kind' => 'proposal_clarification_question',
            'title' => __('Pre-award question'),
            'body' => str($this->message->body)->limit(120)->toString(),
            'href' => route('quests.proposals.clarify', [$quest, $this->offer], absolute: false),
        ];
    }
}
