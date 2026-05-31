<?php

namespace App\Notifications;

use App\Models\ProposalClarificationMessage;
use App\Models\QuestOffer;
use App\Notifications\Concerns\SendsBrandedMail;
use App\Services\ConversationMonitoring\ConversationMessageRedactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalClarificationQuestionNotification extends Notification
{
    use Queueable, SendsBrandedMail;

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
        $preview = app(ConversationMessageRedactionService::class)->publicMessagePayload(
            (string) $this->message->body,
            (bool) $this->message->is_redacted,
            $this->message->redaction_label,
        )['body'];

        return $this->brandedMail(
            subject: __('Pre-award question on :title', ['title' => $quest?->title ?? 'a quest']),
            headline: __('New pre-award question'),
            notifiable: $notifiable,
            lines: [
                __('The client asked a clarifying question before deciding on your proposal:'),
            ],
            panel: '“'.str($preview)->limit(400).'”',
            ctaUrl: route('quests.proposals.clarify', [$quest, $this->offer], absolute: true),
            ctaLabel: __('Answer in clarification thread'),
            footerLine: __('Keep all negotiation on HustleSafe — do not move payments or contact off-platform.'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;
        $preview = app(ConversationMessageRedactionService::class)->publicMessagePayload(
            (string) $this->message->body,
            (bool) $this->message->is_redacted,
            $this->message->redaction_label,
        )['body'];

        return [
            'kind' => 'proposal_clarification_question',
            'title' => __('Pre-award question'),
            'body' => str($preview)->limit(120)->toString(),
            'quest_id' => $quest?->id,
            'offer_id' => $this->offer->id,
            'href' => route('quests.proposals.clarify', [$quest, $this->offer], absolute: false),
        ];
    }
}
