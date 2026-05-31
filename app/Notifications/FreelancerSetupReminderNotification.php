<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreelancerSetupReminderNotification extends Notification
{
    use Queueable, SendsBrandedMail;

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
        $lines = [];
        foreach ($this->summary['blockers'] ?? [] as $b) {
            if (is_array($b) && isset($b['message'])) {
                $lines[] = (string) $b['message'];
            }
        }

        if ($lines === [] && ! ($this->summary['identity_approved'] ?? false)) {
            $lines[] = __('Complete government ID verification so you can bid on larger quests and access withdrawals when payouts are ready.');
        }

        if ($lines === []) {
            $lines[] = __('Finish the remaining profile steps so you can send offers and withdraw safely.');
        }

        return $this->brandedMail(
            subject: __('Finish your freelancer setup on HustleSafe'),
            headline: __('Complete your freelancer profile'),
            notifiable: $notifiable,
            lines: $lines,
            ctaUrl: $this->primaryAccountUrl(),
            ctaLabel: __('Go to the right place'),
        );
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
