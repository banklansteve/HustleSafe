<?php

namespace App\Notifications;

use App\Models\FreelancerSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FreelancerProAdminGrantedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public FreelancerSubscription $subscription,
        public string $adminNote,
    ) {}

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
            ->subject(__('You have been upgraded to HustleSafe Pro'))
            ->greeting(__('Welcome to Pro, :name!', ['name' => $notifiable->first_name ?: $notifiable->name]))
            ->line(__('A super admin has upgraded your account to Pro membership.'))
            ->when($this->adminNote !== '', fn (MailMessage $m) => $m->line(__('Note: :note', ['note' => $this->adminNote])))
            ->action(__('View Pro benefits'), route('freelancer.pro.index', absolute: true));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'freelancer_pro_admin_granted',
            'headline' => __('Upgraded to Pro'),
            'title' => __('You have been upgraded to premium'),
            'body' => $this->adminNote !== '' ? $this->adminNote : __('Pro benefits are now active on your account.'),
            'href' => route('freelancer.pro.index', absolute: false),
        ];
    }
}
