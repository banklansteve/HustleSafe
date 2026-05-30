<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalAwardPendingFreelancerNotification extends Notification
{
    use Queueable;

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
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;
        $first = $notifiable->first_name ?: $notifiable->name;

        return (new MailMessage)
            ->subject(__('Confirm your award on :title', ['title' => $quest?->title ?? 'a quest']))
            ->line(__('Hi :name,', ['name' => $first]))
            ->line(__('The client wants to award you this quest. Please review and confirm the agreed terms:'))
            ->line(__('Price: :price', ['price' => $this->terms['price_label'] ?? '—']))
            ->when(! empty($this->terms['deadline_label']), fn (MailMessage $m) => $m->line(__('Target finish: :date', ['date' => $this->terms['deadline_label']])))
            ->line(__('Scope: :scope', ['scope' => str($this->terms['scope_summary'] ?? '')->limit(200)]))
            ->action(__('Confirm award terms'), route('quests.proposals.show', [$quest, $this->offer], absolute: true))
            ->line(__('Escrow funding only begins after you confirm — this creates a documented contract moment for both sides.'));
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
