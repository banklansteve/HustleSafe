<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Models\QuestContractDeliveryExtension;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractDeliveryExtensionClientNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public QuestContract $contract,
        public QuestContractDeliveryExtension $extension,
        public string $context = 'submitted',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->extension->client_response_deadline_at
            ->timezone(config('app.timezone'))
            ->format('j M Y, H:i');

        $lines = match ($this->context) {
            'submitted' => [
                __('Your freelancer has requested a delivery timeline extension on contract :ref.', ['ref' => $this->contract->reference_code]),
                __('Reason: :category', ['category' => $this->extension->reason_category->label()]),
                __('Proposed new delivery date: :date', ['date' => $this->extension->proposed_delivery_date->format('j M Y')]),
                __('If you do not respond within 48 hours, this extension request will be automatically approved.'),
            ],
            'approved' => [
                __('The delivery date on contract :ref has been updated to :date.', [
                    'ref' => $this->contract->reference_code,
                    'date' => ($this->extension->applied_delivery_date ?? $this->extension->proposed_delivery_date)->format('j M Y'),
                ]),
            ],
            'auto_approved' => [
                __('Because no response was received within 48 hours, the delivery extension on contract :ref was automatically approved.', [
                    'ref' => $this->contract->reference_code,
                ]),
                __('The new agreed delivery date is :date.', [
                    'date' => ($this->extension->applied_delivery_date ?? $this->extension->proposed_delivery_date)->format('j M Y'),
                ]),
            ],
            'counter_rejected' => [
                __('The freelancer declined your counter-proposal. The original delivery deadline remains in effect.'),
            ],
            default => [__('There is an update on the delivery extension for contract :ref.', ['ref' => $this->contract->reference_code])],
        };

        return $this->brandedMail(
            subject: $this->subjectLine(),
            headline: $this->headline(),
            notifiable: $notifiable,
            lines: $lines,
            panel: $this->context === 'submitted' ? $this->extension->explanation : null,
            ctaUrl: route('contracts.show', $this->contract, absolute: true),
            ctaLabel: __('Review contract'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_delivery_extension_'.$this->context,
            'title' => $this->headline(),
            'body' => $this->extension->reason_category->label(),
            'href' => route('contracts.show', $this->contract, absolute: false),
        ];
    }

    private function subjectLine(): string
    {
        return match ($this->context) {
            'submitted' => __('Delivery extension request — :ref', ['ref' => $this->contract->reference_code]),
            'approved' => __('Delivery date updated — :ref', ['ref' => $this->contract->reference_code]),
            'auto_approved' => __('Extension auto-approved — :ref', ['ref' => $this->contract->reference_code]),
            default => __('Delivery extension update — :ref', ['ref' => $this->contract->reference_code]),
        };
    }

    private function headline(): string
    {
        return match ($this->context) {
            'submitted' => __('Delivery extension request'),
            'approved' => __('Delivery date extended'),
            'auto_approved' => __('Extension auto-approved'),
            'counter_rejected' => __('Counter-proposal declined'),
            default => __('Delivery extension update'),
        };
    }
}
