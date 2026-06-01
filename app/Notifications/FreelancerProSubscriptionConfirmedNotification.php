<?php

namespace App\Notifications;

use App\Models\FreelancerSubscription;
use App\Support\NgnMoney;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreelancerProSubscriptionConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(public FreelancerSubscription $subscription) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your HustleSafe Pro membership is active'))
            ->greeting(__('Welcome to Pro, :name!', ['name' => $notifiable->first_name ?: $notifiable->name]))
            ->line(__('Your Pro membership is now active with unlimited proposals, profile badge, and priority visibility.'))
            ->line(__('Renewal date: :date', ['date' => $this->subscription->renewal_date?->timezone('Africa/Lagos')->format('d M Y, H:i') ?? '—']))
            ->line(__('Next charge: :amount (manual renewal — auto-renew is off).', [
                'amount' => NgnMoney::format((int) ($this->subscription->billing_cycle === 'year'
                    ? $this->subscription->annual_price_minor
                    : $this->subscription->monthly_price_minor)),
            ]))
            ->line(__('You can cancel anytime from Account settings. Pro fees are non-refundable.'))
            ->action(__('Manage subscription'), route('freelancer.pro.index', absolute: true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'freelancer_pro_confirmed',
            'headline' => __('Pro membership active'),
            'title' => __('Pro membership active'),
            'body' => __('Unlimited proposals and Pro benefits are now live on your account.'),
            'href' => route('freelancer.pro.index', absolute: false),
            'renewal_date' => $this->subscription->renewal_date?->toIso8601String(),
        ];
    }
}
