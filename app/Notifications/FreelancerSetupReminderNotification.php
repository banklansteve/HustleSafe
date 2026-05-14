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

    public function primaryAccountUrl(): string
    {
        foreach ($this->summary['blockers'] ?? [] as $b) {
            if (is_array($b) && ! empty($b['action_url'])) {
                return (string) $b['action_url'];
            }
        }

        if (! ($this->summary['identity_approved'] ?? false)) {
            return route('verifications.index').'#verification-submit';
        }

        return route('account.show', ['tab' => 'overview']);
    }

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
            ->action(__('Go to the right place'), $this->primaryAccountUrl());
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'headline' => __('Finish your freelancer profile'),
            'title' => __('Profile setup'),
            'body' => __('We still need a few details so you can send offers and withdraw safely.'),
            'href' => $this->primaryAccountUrl(),
        ];
    }
}
