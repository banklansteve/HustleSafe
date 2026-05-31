<?php

namespace App\Listeners;

use App\Events\UserInboxNotificationCreated;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Broadcast;

class BroadcastUserDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (! $event->notifiable instanceof User) {
            return;
        }

        if (! Broadcast::getDefaultDriver() || Broadcast::getDefaultDriver() === 'null') {
            return;
        }

        $notificationId = is_string($event->response) ? $event->response : null;
        if ($notificationId === null || $notificationId === '') {
            $latest = $event->notifiable->notifications()->latest()->first();
            $notificationId = $latest?->id;
        }

        if ($notificationId === null || $notificationId === '') {
            return;
        }

        UserInboxNotificationCreated::dispatch(
            (int) $event->notifiable->getKey(),
            $notificationId,
        );
    }
}
