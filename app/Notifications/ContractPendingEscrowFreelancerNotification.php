<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractPendingEscrowFreelancerNotification extends Notification
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
            subject: __('Contract on hold — :ref', ['ref' => $this->contract->reference_code]),
            headline: __('Contract generated — awaiting escrow'),
            notifiable: $notifiable,
            lines: [
                __('A contract has been generated for your awarded quest. Work starts once the client funds escrow.'),
            ],
            ctaUrl: route('contracts.show', $this->contract, absolute: true),
            ctaLabel: __('View contract'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_pending_escrow',
            'title' => __('Contract on hold pending escrow'),
            'body' => __('Contract :ref awaits client escrow funding.', ['ref' => $this->contract->reference_code]),
            'href' => route('contracts.show', $this->contract, absolute: false),
            'contract_reference' => $this->contract->reference_code,
        ];
    }
}
