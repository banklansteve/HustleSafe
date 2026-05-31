<?php

namespace App\Events;

use App\Support\Broadcasting\UsesDefaultBroadcastConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UserInboxNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, UsesDefaultBroadcastConnection;

    public function __construct(
        public int $userId,
        public string $notificationId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'inbox.notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notificationId,
        ];
    }
}
