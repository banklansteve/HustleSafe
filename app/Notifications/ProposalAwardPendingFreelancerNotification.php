<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalAwardPendingFreelancerNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    /**
     * @param  array<string, mixed>  $terms
     */
    public function __construct(
        public QuestOffer $offer,
        public array $terms,
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
        $this->offer->loadMissing(['quest.client']);
        $quest = $this->offer->quest;
        $panel = collect([
            __('Client: :name', ['name' => $quest?->client?->name ?? __('The client')]),
            __('Your payout on release: :amount', ['amount' => $this->terms['payout']['net_to_wallet_label'] ?? $this->terms['price_label'] ?? '—']),
            ! empty($this->terms['deadline_label']) ? __('Finish by: :date', ['date' => $this->terms['deadline_label']]) : null,
        ])->filter()->implode("\n\n");

        return $this->brandedMail(
            subject: __('Confirm your award on :title', ['title' => $quest?->title ?? 'a quest']),
            headline: __('Confirm your award terms'),
            notifiable: $notifiable,
            lines: [
                __('The client wants to award you this quest. Please review and confirm the agreed terms below.'),
            ],
            panel: $panel,
            ctaUrl: route('quests.proposals.show', [$quest, $this->offer], absolute: true),
            ctaLabel: __('Confirm award terms'),
            footerLine: __('Escrow funding only begins after you confirm — this creates a documented contract moment for both sides.'),
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
            'kind' => 'proposal_award_pending_freelancer',
            'title' => __('Confirm award terms'),
            'body' => __('The client selected you — confirm scope, price, and deadline to proceed to escrow.'),
            'href' => route('quests.proposals.show', [$quest, $this->offer], absolute: false),
        ];
    }
}
