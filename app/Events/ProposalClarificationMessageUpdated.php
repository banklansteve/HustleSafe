<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalClarificationMessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $threadMeta
     */
    public function __construct(
        public int $threadId,
        public array $message,
        public array $threadMeta = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('proposal-clarifications.'.$this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'clarification.message.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->threadId,
            'message' => $this->message,
            'thread' => $this->threadMeta,
        ];
    }
}
