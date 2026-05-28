<?php

namespace App\Console\Commands;

use App\Services\ReviewModeration\ReviewManipulationReportService;
use Illuminate\Console\Command;

class RefreshReviewManipulationReportsCommand extends Command
{
    protected $signature = 'review-manipulation:refresh';

    protected $description = 'Rebuild nightly review manipulation dashboard snapshots';

    public function handle(ReviewManipulationReportService $service): int
    {
        $service->refreshAll();
        $this->info('Review manipulation reports refreshed.');

        return self::SUCCESS;
    }
}
