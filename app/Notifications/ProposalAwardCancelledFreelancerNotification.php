<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProposalAwardCancelledFreelancerNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public QuestOffer $offer,
        public ?string $reason = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $this->offer->loadMissing(['quest', 'quest.client']);
        $quest = $this->offer->quest;
        $clientName = $quest?->client?->name ?? __('The client');

        $lines = [
            __(':name cancelled the award on “:title” before escrow was funded, so the job has not started.', [
                'name' => $clientName,
                'title' => $quest?->title ?? __('the quest'),
            ]),
        ];

        if ($this->reason) {
            $lines[] = __('Reason given: :reason', ['reason' => $this->reason]);
        }

        $lines[] = __('You can keep proposing on open quests — no payment was taken and no contract work was due.');

        return $this->brandedMail(
            subject: __('Award cancelled on :title', ['title' => $quest?->title ?? __('a quest')]),
            headline: __('Award withdrawn before start'),
            notifiable: $notifiable,
            lines: $lines,
            ctaUrl: route('quests.explore', absolute: true),
            ctaLabel: __('Browse open quests'),
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
            'kind' => 'proposal_award_cancelled_freelancer',
            'headline' => __('Award cancelled'),
            'title' => __('Award cancelled'),
            'quest_title' => $quest?->title,
            'body' => __('The client cancelled the award before escrow was funded.'),
            'href' => route('quests.explore', absolute: false),
        ];
    }
}
