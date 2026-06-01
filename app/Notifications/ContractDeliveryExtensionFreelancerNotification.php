<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Models\QuestContractDeliveryExtension;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractDeliveryExtensionFreelancerNotification extends Notification
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
        $lines = match ($this->context) {
            'declined' => [
                __('The client declined your delivery extension request on contract :ref.', ['ref' => $this->contract->reference_code]),
                __('The original delivery deadline remains in effect.'),
            ],
            'counter_proposed' => [
                __('The client counter-proposed a delivery date of :date on contract :ref.', [
                    'date' => $this->extension->counter_proposed_date?->format('j M Y'),
                    'ref' => $this->contract->reference_code,
                ]),
                __('You have 24 hours to accept or decline this counter-proposal.'),
            ],
            'counter_expired' => [
                __('Your counter-proposal response window expired. The original delivery deadline has been reinstated.'),
            ],
            'approved' => [
                __('Your delivery extension was approved. The new agreed delivery date is :date.', [
                    'date' => ($this->extension->applied_delivery_date ?? $this->extension->proposed_delivery_date)->format('j M Y'),
                ]),
            ],
            default => [__('Update on your delivery extension request for contract :ref.', ['ref' => $this->contract->reference_code])],
        };

        return $this->brandedMail(
            subject: $this->subjectLine(),
            headline: $this->headline(),
            notifiable: $notifiable,
            lines: $lines,
            panel: $this->extension->decline_reason,
            ctaUrl: route('contracts.show', $this->contract, absolute: true),
            ctaLabel: __('Open contract'),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_delivery_extension_freelancer_'.$this->context,
            'title' => $this->headline(),
            'body' => $this->extension->reason_category->label(),
            'href' => route('contracts.show', $this->contract, absolute: false),
        ];
    }

    private function subjectLine(): string
    {
        return match ($this->context) {
            'declined' => __('Extension declined — :ref', ['ref' => $this->contract->reference_code]),
            'counter_proposed' => __('Counter-proposal received — :ref', ['ref' => $this->contract->reference_code]),
            'approved' => __('Extension approved — :ref', ['ref' => $this->contract->reference_code]),
            default => __('Extension update — :ref', ['ref' => $this->contract->reference_code]),
        };
    }

    private function headline(): string
    {
        return match ($this->context) {
            'declined' => __('Extension request declined'),
            'counter_proposed' => __('Client counter-proposed a date'),
            'counter_expired' => __('Counter-proposal expired'),
            'approved' => __('Extension approved'),
            default => __('Extension update'),
        };
    }
}
