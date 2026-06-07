<?php

namespace App\Console\Commands;

use App\Services\Admin\PremiumPatrol\PremiumPatrolAnomalyService;
use Illuminate\Console\Command;

class ScanPremiumPatrolAnomaliesCommand extends Command
{
    protected $signature = 'premium-patrol:scan-anomalies';

    protected $description = 'Scan premium subscriptions and quest boosts for anomaly patterns';

    public function handle(PremiumPatrolAnomalyService $anomalies): int
    {
        $created = $anomalies->scanAll();
        $this->info("Premium patrol scan complete. {$created} new flag(s).");

        return self::SUCCESS;
    }
}
