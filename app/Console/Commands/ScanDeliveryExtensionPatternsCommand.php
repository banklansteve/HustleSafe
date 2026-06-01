<?php

namespace App\Console\Commands;

use App\Services\Contracts\DeliveryExtensionPatternDetector;
use Illuminate\Console\Command;

class ScanDeliveryExtensionPatternsCommand extends Command
{
    protected $signature = 'contracts:scan-extension-patterns';

    protected $description = 'Detect freelancers with repeated delivery extension patterns';

    public function handle(DeliveryExtensionPatternDetector $detector): int
    {
        $result = $detector->run();
        $this->info(sprintf('Scanned %d freelancers, flagged %d.', $result['scanned'], $result['flagged']));

        return self::SUCCESS;
    }
}
