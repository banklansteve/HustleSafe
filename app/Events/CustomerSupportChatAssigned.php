<?php

namespace App\Events;

use App\Support\Broadcasting\UsesDefaultBroadcastConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerSupportChatAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, UsesDefaultBroadcastConnection;

    /**
     * @param  array<string, mixed>  $ticket
     */
    public function __construct(
        public int $adminUserId,
        public array $ticket,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->adminUserId)];
    }

    public function broadcastAs(): string
    {
        return 'support.chat.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket' => $this->ticket,
            'url' => route('admin.customer-support.index', ['ticket' => $this->ticket['id']]),
        ];
    }
}
