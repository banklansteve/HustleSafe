<?php

namespace App\Console\Commands;

use App\Services\Operations\ProactiveOutreachScannerService;
use Illuminate\Console\Command;

class ScanProactiveOutreachCommand extends Command
{
    protected $signature = 'operations:scan-proactive-outreach';

    protected $description = 'Detect retention and trust situations for the proactive outreach queue.';

    public function handle(ProactiveOutreachScannerService $scanner): int
    {
        $counts = $scanner->run();
        $total = array_sum($counts);

        foreach ($counts as $key => $count) {
            $this->line("  {$key}: {$count} new");
        }

        $this->info("Proactive outreach scan complete. {$total} new item(s).");

        return self::SUCCESS;
    }
}
