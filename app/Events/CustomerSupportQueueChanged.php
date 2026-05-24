<?php

namespace App\Events;

use App\Support\Broadcasting\UsesDefaultBroadcastConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerSupportQueueChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, UsesDefaultBroadcastConnection;

    /**
     * @param  array<string, mixed>  $ticket
     */
    public function __construct(
        public array $ticket,
        public string $action = 'updated',
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer-support.staff')];
    }

    public function broadcastAs(): string
    {
        return 'queue.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'ticket' => $this->ticket,
        ];
    }
}
