<?php

namespace App\Notifications;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PortfolioFavoritedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Portfolio $portfolio,
        protected User $fan,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $label = $this->fan->first_name ?: $this->fan->name ?: __('Someone');

        return [
            'message' => __(':name favourited your portfolio “:title”.', [
                'name' => $label,
                'title' => Str::limit($this->portfolio->title, 80),
            ]),
            'portfolio_slug' => $this->portfolio->slug,
            'portfolio_title' => $this->portfolio->title,
            'fan_id' => $this->fan->id,
            'type' => 'portfolio_favorited',
        ];
    }
}
