<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Models\QuestContractAmendment;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractAmendmentRequestedNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(public QuestContract $contract, public QuestContractAmendment $amendment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            subject: __('Amendment requested — :ref', ['ref' => $this->contract->reference_code]),
            headline: __('Contract amendment needs your response'),
            notifiable: $notifiable,
            lines: [
                __('The other party requested a :type amendment on contract :ref.', [
                    'type' => strtolower($this->amendment->amendment_type->label()),
                    'ref' => $this->contract->reference_code,
                ]),
                $this->amendment->description,
            ],
            panel: $this->amendment->reason,
            ctaUrl: route('contracts.show', $this->contract, absolute: true),
            ctaLabel: __('Review contract'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_amendment_requested',
            'title' => __('Contract amendment requested'),
            'body' => $this->amendment->amendment_type->label(),
            'href' => route('contracts.show', $this->contract, absolute: false),
        ];
    }
}
