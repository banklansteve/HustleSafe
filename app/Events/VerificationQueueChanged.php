<?php

namespace App\Events;

use App\Support\Broadcasting\UsesDefaultBroadcastConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class VerificationQueueChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, UsesDefaultBroadcastConnection;

    /**
     * @param  array<string, mixed>  $verification
     */
    public function __construct(
        public array $verification,
        public string $action = 'updated',
        public int $assignedStaffId = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('verification.staff')];
    }

    public function broadcastAs(): string
    {
        return 'queue.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'verification' => $this->verification,
            'assigned_staff_id' => $this->assignedStaffId,
        ];
    }
}
