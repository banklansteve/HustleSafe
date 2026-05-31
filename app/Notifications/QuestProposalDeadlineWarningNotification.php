<?php

namespace App\Notifications;

use App\Models\Quest;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestProposalDeadlineWarningNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public Quest $quest,
        public int $proposalsReceived,
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
        $expires = $this->quest->listing_expires_at?->timezone('Africa/Lagos')->format('j M Y, g:i A') ?? __('soon');

        return $this->brandedMail(
            subject: __('Your quest closes soon — time to review proposals'),
            headline: __('Review proposals before the deadline'),
            notifiable: $notifiable,
            lines: [
                __('Your quest “:title” stops accepting proposals on :when.', [
                    'title' => $this->quest->title,
                    'when' => $expires,
                ]),
                $this->proposalsReceived > 0
                    ? trans_choice('You have :count proposal to review.|You have :count proposals to review.', $this->proposalsReceived, ['count' => $this->proposalsReceived])
                    : __('You have not received proposals yet — consider adjusting your brief or extending once if needed.'),
                __('Shortlist your favourites and award a freelancer before the deadline to keep momentum.'),
            ],
            ctaUrl: route('quests.show', $this->quest, absolute: true),
            ctaLabel: __('Review proposals'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'quest_proposal_deadline_warning',
            'quest_id' => $this->quest->id,
            'quest_title' => $this->quest->title,
            'proposalsReceived' => $this->proposalsReceived,
            'expires_at' => $this->quest->listing_expires_at?->toIso8601String(),
            'label' => __('Quest closing soon'),
            'line' => trans_choice(
                ':count proposal waiting — review before :title closes.',
                $this->proposalsReceived,
                ['count' => $this->proposalsReceived, 'title' => $this->quest->title],
            ),
            'href' => route('quests.show', $this->quest, absolute: false),
        ];
    }
}
