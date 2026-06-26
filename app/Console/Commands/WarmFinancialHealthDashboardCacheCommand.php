<?php

namespace App\Console\Commands;

use App\Services\Admin\FinancialHealthDashboardService;
use Illuminate\Console\Command;

class WarmFinancialHealthDashboardCacheCommand extends Command
{
    protected $signature = 'financial:warm-health-dashboard';

    protected $description = 'Pre-compute and cache financial health dashboard metrics and charts';

    public function handle(FinancialHealthDashboardService $service): int
    {
        foreach (['today', 'week', 'month'] as $period) {
            $service->warmCache($period);
            $this->info("Warmed financial health cache for period: {$period}");
        }

        return self::SUCCESS;
    }
}
