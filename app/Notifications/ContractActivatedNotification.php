<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractActivatedNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(public QuestContract $contract) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            subject: __('Contract active — :ref', ['ref' => $this->contract->reference_code]),
            headline: __('Your contract is now active'),
            notifiable: $notifiable,
            lines: [
                __('Escrow is funded and contract :ref is now active. You can view the full agreement, timeline, and terms on your contract page.', [
                    'ref' => $this->contract->reference_code,
                ]),
            ],
            ctaUrl: route('contracts.show', $this->contract, absolute: true),
            ctaLabel: __('Open contract'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_activated',
            'title' => __('Contract activated'),
            'body' => __('Contract :ref is now active.', ['ref' => $this->contract->reference_code]),
            'href' => route('contracts.show', $this->contract, absolute: false),
            'contract_reference' => $this->contract->reference_code,
        ];
    }
}
