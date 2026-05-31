<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminUserMessageNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public string $subject,
        public string $message,
    ) {}

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
            lines: [$this->message],
        );
    }
}
