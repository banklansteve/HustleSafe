<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreelancerSetupReminderNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(public array $summary) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Finish your freelancer setup on HustleSafe'));

        foreach ($this->summary['blockers'] ?? [] as $b) {
            if (is_array($b) && isset($b['message'])) {
                $mail->line($b['message']);
            }
        }

        if (($this->summary['blockers'] ?? []) === [] && ! ($this->summary['identity_approved'] ?? false)) {
            $mail->line(__('Complete government ID verification so you can bid on larger quests and access withdrawals when payouts are ready.'));
        }

        return $mail
            ->action(__('Open your account'), route('account.show', ['tab' => 'overview']));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Profile setup'),
            'body' => __('We still need a few details so you can send offers and withdraw safely.'),
            'href' => route('account.show', ['tab' => 'overview']),
        ];
    }
}
