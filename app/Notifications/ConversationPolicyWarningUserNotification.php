<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversationPolicyWarningUserNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public readonly string $subject,
        public readonly string $messageBody,
        public readonly ?int $warningId = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            subject: $this->subject,
            headline: __('Policy notice'),
            notifiable: $notifiable,
            lines: [
                $this->messageBody,
                __('All communication and payments must stay on HustleSafe. Repeated violations may lead to suspension.'),
            ],
            ctaUrl: route('account.policy-notices.index', absolute: true),
            ctaLabel: __('View notice'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'conversation_policy_warning',
            'warning_id' => $this->warningId,
            'title' => $this->subject,
            'body' => str($this->messageBody)->limit(180)->toString(),
            'href' => route('account.policy-notices.index', absolute: false),
        ];
    }
}
