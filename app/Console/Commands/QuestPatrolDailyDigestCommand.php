<?php

namespace App\Console\Commands;

use App\Services\Admin\QuestPatrol\QuestPatrolDigestService;
use Illuminate\Console\Command;

class QuestPatrolDailyDigestCommand extends Command
{
    protected $signature = 'quest-patrol:daily-digest';

    protected $description = 'Email super admins a daily quest & proposal patrol anomaly digest';

    public function handle(QuestPatrolDigestService $digest): int
    {
        $sent = $digest->sendDailyDigest();
        $this->info("Quest patrol digest queued for {$sent} super admin(s).");

        return self::SUCCESS;
    }
}
