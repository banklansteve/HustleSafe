<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserFollowedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected User $follower,
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
        $name = $this->follower->first_name ?: $this->follower->name ?: __('Someone');

        return [
            'message' => __(':name started following you.', ['name' => $name]),
            'follower_id' => $this->follower->id,
            'follower_slug' => $this->follower->slug,
            'type' => 'user_followed',
        ];
    }
}
