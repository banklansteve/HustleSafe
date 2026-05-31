<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Notifications\Concerns\SendsBrandedMail;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractPendingEscrowClientNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(public QuestContract $contract) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = PlatformSettings::contractEscrowFundingHours();

        return $this->brandedMail(
            subject: __('Fund escrow for contract :ref', ['ref' => $this->contract->reference_code]),
            headline: __('Fund escrow to activate your contract'),
            notifiable: $notifiable,
            lines: [
                __('Your contract :ref is ready. Fund escrow within :hours hours to activate it and allow work to begin.', [
                    'ref' => $this->contract->reference_code,
                    'hours' => $hours,
                ]),
            ],
            ctaUrl: route('contracts.show', $this->contract, absolute: true),
            ctaLabel: __('View contract & fund escrow'),
            footerLine: __('Funds stay in escrow until you confirm delivery or the agreed review window expires.'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_pending_escrow',
            'title' => __('Fund escrow to activate your contract'),
            'body' => __('Contract :ref is waiting for escrow funding.', ['ref' => $this->contract->reference_code]),
            'href' => route('contracts.show', $this->contract, absolute: false),
            'contract_reference' => $this->contract->reference_code,
        ];
    }
}
