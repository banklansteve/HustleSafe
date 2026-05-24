<?php

namespace App\Events;

use App\Support\Broadcasting\UsesDefaultBroadcastConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerSupportTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, UsesDefaultBroadcastConnection;

    public function __construct(
        public int $ticketId,
        public int $userId,
        public string $name,
        public bool $typing,
        public string $side,
        public ?string $firstName = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customer-support.'.$this->ticketId),
            new PrivateChannel('customer-support.staff'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'user_id' => $this->userId,
            'name' => $this->name,
            'first_name' => $this->firstName,
            'typing' => $this->typing,
            'side' => $this->side,
        ];
    }
}
