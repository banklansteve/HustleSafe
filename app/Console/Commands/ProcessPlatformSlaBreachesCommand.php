<?php

namespace App\Console\Commands;

use App\Services\Platform\PlatformSlaService;
use Illuminate\Console\Command;

class ProcessPlatformSlaBreachesCommand extends Command
{
    protected $signature = 'platform-sla:process-breaches';

    protected $description = 'Mark overdue platform SLA clocks as breached and escalate to Super Admin';

    public function handle(PlatformSlaService $sla): int
    {
        $count = $sla->processBreaches();
        $this->info("Processed {$count} SLA breach(es).");

        return self::SUCCESS;
    }
}
