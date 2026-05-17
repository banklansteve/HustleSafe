<?php

namespace App\Events;

use App\Models\AdminActivityFeedEvent;
use App\Services\Admin\AdminActivityFeedService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminActivityFeedEventCreated implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public AdminActivityFeedEvent $event
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.live-activity');
    }

    public function broadcastAs(): string
    {
        return 'event.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event' => app(AdminActivityFeedService::class)->serialize($this->event),
        ];
    }
}
