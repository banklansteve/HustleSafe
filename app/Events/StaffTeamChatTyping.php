<?php

namespace App\Events;

use App\Support\Broadcasting\UsesDefaultBroadcastConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class StaffTeamChatTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, UsesDefaultBroadcastConnection;

    public function __construct(
        public int $roomId,
        public int $userId,
        public string $userName,
        public bool $typing,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('staff-team.'.$this->roomId)];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'typing' => $this->typing,
        ];
    }
}
