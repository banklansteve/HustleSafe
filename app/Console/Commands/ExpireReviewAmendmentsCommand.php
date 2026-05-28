<?php

namespace App\Console\Commands;

use App\Services\ReviewModeration\ReviewAmendmentService;
use Illuminate\Console\Command;

class ExpireReviewAmendmentsCommand extends Command
{
    protected $signature = 'review-amendments:expire';

    protected $description = 'Apply default actions for expired review amendment requests';

    public function handle(ReviewAmendmentService $service): int
    {
        $count = $service->expireOpenRequests();
        $this->info("Processed {$count} expired amendment request(s).");

        return self::SUCCESS;
    }
}
