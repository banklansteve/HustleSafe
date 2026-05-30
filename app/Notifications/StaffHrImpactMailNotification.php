<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffHrImpactMailNotification extends Notification
{
    use Queueable;

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
        $message = (new MailMessage)
            ->subject($this->subject)
            ->greeting('Hello '.$notifiable->name.',');

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        if ($this->actionUrl !== null && $this->actionText !== null) {
            $message->action($this->actionText, $this->actionUrl);
        }

        return $message;
    }
}
