<?php

namespace App\Notifications;

use App\Models\ProposalClarificationMessage;
use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalClarificationAnswerNotification extends Notification
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

        return (new MailMessage)
            ->subject(__('Clarification answered — :title', ['title' => $quest?->title ?? 'your quest']))
            ->line(__('The freelancer replied to your pre-award question.'))
            ->line('“'.str($this->message->body)->limit(400).'”')
            ->action(__('View thread'), route('quests.proposals.clarify', [$quest, $this->offer], absolute: true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;

        return [
            'kind' => 'proposal_clarification_answer',
            'title' => __('Clarification answered'),
            'body' => str($this->message->body)->limit(120)->toString(),
            'href' => route('quests.proposals.clarify', [$quest, $this->offer], absolute: false),
        ];
    }
}
