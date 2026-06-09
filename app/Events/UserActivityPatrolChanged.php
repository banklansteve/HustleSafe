<?php

namespace App\Events;

use App\Support\Broadcasting\UsesDefaultBroadcastConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UserActivityPatrolChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, UsesDefaultBroadcastConnection;

    public function __construct(
        public string $action = 'updated',
        public ?int $userId = null,
        public ?int $flagId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user-activity-patrol.staff')];
    }

    public function broadcastAs(): string
    {
        return 'patrol.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'user_id' => $this->userId,
            'flag_id' => $this->flagId,
            'at' => now()->toIso8601String(),
        ];
    }
}
