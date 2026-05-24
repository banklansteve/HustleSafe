<?php

namespace App\Events;

use App\Support\Broadcasting\UsesDefaultBroadcastConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerSupportSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, UsesDefaultBroadcastConnection;

    /**
     * @param  array<string, mixed>  $ticket
     */
    public function __construct(
        public int $ticketId,
        public array $ticket,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer-support.'.$this->ticketId)];
    }

    public function broadcastAs(): string
    {
        return 'session.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'ticket' => $this->ticket,
        ];
    }
}
