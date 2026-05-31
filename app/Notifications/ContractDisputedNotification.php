<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Models\QuestDispute;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractDisputedNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(public QuestContract $contract, public QuestDispute $dispute) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            subject: __('Contract disputed — :ref', ['ref' => $this->contract->reference_code]),
            headline: __('Contract under dispute'),
            notifiable: $notifiable,
            lines: [
                __('Contract :ref is under dispute. Escrow is frozen while the case is reviewed.', [
                    'ref' => $this->contract->reference_code,
                ]),
            ],
            ctaUrl: route('contracts.show', $this->contract, absolute: true),
            ctaLabel: __('View contract'),
            footerLine: __('You can also open the dispute case from your contract page.'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_disputed',
            'title' => __('Contract disputed'),
            'body' => __('Contract :ref is under dispute.', ['ref' => $this->contract->reference_code]),
            'href' => route('contracts.show', $this->contract, absolute: false),
        ];
    }
}
