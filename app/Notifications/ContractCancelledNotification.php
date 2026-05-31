<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractCancelledNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(public QuestContract $contract, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            subject: __('Contract cancelled — :ref', ['ref' => $this->contract->reference_code]),
            headline: __('Contract cancelled'),
            notifiable: $notifiable,
            lines: [
                __('Contract :ref was cancelled before escrow was funded.', ['ref' => $this->contract->reference_code]),
            ],
            panel: $this->reason,
            ctaUrl: route('contracts.show', $this->contract, absolute: true),
            ctaLabel: __('View contract record'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_cancelled',
            'title' => __('Contract cancelled'),
            'body' => $this->reason,
            'href' => route('contracts.show', $this->contract, absolute: false),
        ];
    }
}
