<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractCompletedNotification extends Notification
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
            subject: __('Contract completed — :ref', ['ref' => $this->contract->reference_code]),
            headline: __('Contract completed'),
            notifiable: $notifiable,
            lines: [
                __('Contract :ref is complete. The agreement is now read-only and retained for your records.', [
                    'ref' => $this->contract->reference_code,
                ]),
            ],
            ctaUrl: route('contracts.show', $this->contract, absolute: true),
            ctaLabel: __('View contract'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_completed',
            'title' => __('Contract completed'),
            'body' => __('Contract :ref is complete and read-only.', ['ref' => $this->contract->reference_code]),
            'href' => route('contracts.show', $this->contract, absolute: false),
        ];
    }
}
