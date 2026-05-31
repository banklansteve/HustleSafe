<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffHrImpactMailNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        private readonly string $subject,
        private readonly array $lines,
        private readonly ?string $actionUrl = null,
        private readonly ?string $actionText = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(
            subject: $this->subject,
            headline: $this->subject,
            notifiable: $notifiable,
            lines: $this->lines,
            ctaUrl: $this->actionUrl,
            ctaLabel: $this->actionText,
        );
    }
}
