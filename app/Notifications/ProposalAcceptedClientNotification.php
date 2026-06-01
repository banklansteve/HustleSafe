<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use App\Support\PlatformFeeDisclosure;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalAcceptedClientNotification extends Notification
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
        $first = $notifiable->first_name ?: $notifiable->name;
        $grand = (int) ($this->offer->quoted_amount_minor ?? 0);
        $feePct = PlatformSettings::platformFeePercent();
        $disclosure = PlatformFeeDisclosure::toArray($feePct);

        return (new MailMessage)
            ->subject(__('Next step: fund escrow for :title', ['title' => $quest?->title ?? 'your quest']))
            ->markdown('mail.quests.proposal-accepted-client', [
                'firstName' => $first,
                'questTitle' => $quest?->title,
                'freelancerName' => $this->offer->freelancer?->name ?? __('Freelancer'),
                'grandNgn' => number_format($grand / 100, 0, '.', ','),
                'feePercent' => $disclosure['platform_fee_percent_label'],
                'feeDisclosure' => $disclosure,
                'feeSummaryLines' => PlatformFeeDisclosure::summaryLines($feePct),
                'termsUrl' => route('legal.terms', absolute: true),
                'questUrl' => $quest ? route('quests.show', $quest, absolute: true) : url('/'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing('quest');
        $quest = $this->offer->quest;
        $href = $quest ? route('quests.show', $quest, absolute: false) : '/';

        return [
            'kind' => 'proposal_accepted_client',
            'headline' => __('Proposal accepted — fund escrow'),
            'title' => __('Proposal accepted — fund escrow'),
            'quest_title' => $quest?->title,
            'body' => __('You accepted a proposal. Fund escrow (including fees) before work is authorised.'),
            'href' => $href,
        ];
    }
}
