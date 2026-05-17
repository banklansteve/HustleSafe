<?php

namespace App\Console\Commands;

use App\Models\AdminActivityFeedEvent;
use Illuminate\Console\Command;

class PruneAdminActivityFeedCommand extends Command
{
    protected $signature = 'admin-activity-feed:prune';

    protected $description = 'Prune admin activity feed events older than 90 days.';

    public function handle(): int
    {
        $deleted = AdminActivityFeedEvent::query()
            ->where('occurred_at', '<', now()->subDays(90))
            ->delete();

        $this->info("Pruned {$deleted} admin activity feed events.");

        return self::SUCCESS;
    }
}
