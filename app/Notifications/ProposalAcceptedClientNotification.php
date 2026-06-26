<?php

namespace App\Notifications;

use App\Models\QuestOffer;
use App\Notifications\Concerns\SendsBrandedMail;
use App\Services\Proposals\ProposalClarificationPromptService;
use App\Support\NgnMoney;
use App\Support\PlatformFeeDisclosure;
use App\Support\PlatformSettings;
use App\Support\ProposalMoneyCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProposalAcceptedClientNotification extends Notification
{
    use Queueable, SendsBrandedMail;

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

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $this->offer->loadMissing(['quest', 'freelancer']);
        $quest = $this->offer->quest;
        $freelancerName = $this->offer->freelancer?->name ?? __('The freelancer');
        $payout = app(ProposalClarificationPromptService::class)->awardPayoutBreakdown($this->offer);
        $snapshot = is_array($this->offer->pricing_snapshot) ? $this->offer->pricing_snapshot : [];
        $escrowMinor = ProposalMoneyCalculator::escrowTotalMinor($snapshot);
        $feePct = PlatformSettings::platformFeePercent();
        $disclosure = PlatformFeeDisclosure::toArray($feePct);
        $questUrl = $quest ? route('quests.show', $quest, absolute: true) : url('/');

        $panel = collect([
            __('Worker: :name', ['name' => $freelancerName]),
            __('Escrow to fund: :amount', ['amount' => NgnMoney::format($escrowMinor)]),
            __('Platform fee on release: :pct% (:fee)', [
                'pct' => $disclosure['platform_fee_percent_label'],
                'fee' => NgnMoney::format((int) ($snapshot['platform_fee_minor'] ?? 0)),
            ]),
            __('Worker receives in wallet: :net', ['net' => $payout['net_to_wallet_label']]),
        ])->implode("\n\n");

        return $this->brandedMail(
            subject: __(':name confirmed — fund escrow for :title', [
                'name' => $freelancerName,
                'title' => $quest?->title ?? __('your quest'),
            ]),
            headline: __('Freelancer confirmed the award'),
            notifiable: $notifiable,
            lines: [
                __(':name has confirmed the award terms for “:title”. You can now fund escrow so work can begin.', [
                    'name' => $freelancerName,
                    'title' => $quest?->title ?? __('your quest'),
                ]),
                __('Fund the full agreed job amount into escrow. Payment stays protected until you approve delivery (or the review period ends with no complaint).'),
            ],
            panel: $panel,
            ctaUrl: $questUrl,
            ctaLabel: __('Open quest & fund escrow'),
            footerLine: __('Thanks for keeping payments on-platform — it protects both sides.'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->offer->loadMissing(['quest', 'freelancer']);
        $quest = $this->offer->quest;
        $freelancerName = $this->offer->freelancer?->name ?? __('The freelancer');
        $href = $quest ? route('quests.show', $quest, absolute: false) : '/';

        return [
            'kind' => 'proposal_award_confirmed_client',
            'headline' => __('Freelancer confirmed award'),
            'title' => __('Freelancer confirmed award'),
            'quest_title' => $quest?->title,
            'freelancer_name' => $freelancerName,
            'body' => __(':name confirmed the award terms. Fund escrow to start work.', ['name' => $freelancerName]),
            'href' => $href,
        ];
    }
}
