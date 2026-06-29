<?php

namespace App\Notifications;

use App\Models\QuestDispute;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestDisputeUpdatedNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public QuestDispute $dispute,
        public string $headline,
        public string $body,
        public ?string $detailMessage = null,
        public ?string $ctaLabel = null,
        public string $delivery = 'database',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return match ($this->delivery) {
            'mail' => ['mail'],
            'both' => ['database', 'mail'],
            default => ['database'],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->dispute->loadMissing('quest');
        $questTitle = $this->dispute->quest?->title ?? __('your contract');

        $lines = [
            $this->body,
            __('Job: :title', ['title' => $questTitle]),
            __('Reference: :ref', ['ref' => $this->dispute->displayReference()]),
        ];

        return $this->brandedMail(
            subject: $this->headline,
            headline: $this->headline,
            notifiable: $notifiable,
            lines: $lines,
            panel: $this->detailMessage,
            ctaUrl: route('disputes.show', $this->dispute, true),
            ctaLabel: $this->ctaLabel ?? __('View dispute'),
            footerLine: __('HustleSafe does not charge dispute resolution fees. Accounts involved in more than three disputes may be reviewed for trust and safety.'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->dispute->loadMissing('quest');

        return [
            'kind' => 'quest_dispute_update',
            'headline' => $this->headline,
            'title' => $this->headline,
            'body' => $this->detailMessage ? "{$this->body}\n\n{$this->detailMessage}" : $this->body,
            'dispute_uuid' => $this->dispute->uuid,
            'quest_title' => $this->dispute->quest?->title,
            'href' => route('disputes.show', $this->dispute, absolute: false),
            'action_label' => $this->ctaLabel ?? __('View dispute'),
        ];
    }
}
