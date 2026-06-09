<?php

namespace App\Console\Commands;

use App\Services\Admin\UserActivityPatrol\UserActivityPatrolAnomalyService;
use Illuminate\Console\Command;

class ScanUserActivityPatrolAnomaliesCommand extends Command
{
    protected $signature = 'user-activity-patrol:scan-anomalies';

    protected $description = 'Scan users for activity anomalies and create patrol flags';

    public function handle(UserActivityPatrolAnomalyService $anomalies): int
    {
        $created = $anomalies->scanAll();
        $this->info("User activity patrol scan complete. {$created} new flag(s) created.");

        return self::SUCCESS;
    }
}
