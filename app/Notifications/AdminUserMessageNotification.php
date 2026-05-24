<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminUserMessageNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private readonly string $subject,
        private readonly string $message,
        private readonly array $meta = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->line($this->message);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return array_merge([
            'title' => $this->subject,
            'body' => $this->message,
            'source' => 'admin_team',
        ], $this->meta);
    }
}
