<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = explode(' ', (string) $notifiable->name)[0] ?: $notifiable->name;

        return (new MailMessage)
            ->subject($this->title)
            ->markdown('mail.verification.decision', [
                'firstName' => $firstName,
                'headline' => $this->title,
                'body' => $this->body,
                'reason' => $this->reason,
                'verificationLabel' => __('Account verification'),
                'statusLabel' => $this->title,
                'ctaUrl' => route('verifications.index'),
                'ctaLabel' => __('View verification status'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'source' => 'kyc_team',
            'category' => 'kyc',
            'action_url' => route('verifications.index'),
        ];
    }
}
