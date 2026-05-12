<?php

namespace App\Console\Commands;

use App\Enums\ReviewStatus;
use App\Models\Review;
use Illuminate\Console\Command;

class LockExpiredReviews extends Command
{
    protected $signature = 'reviews:lock-expired';

    protected $description = 'Lock reviews whose edit window has passed';

    public function handle(): int
    {
        $count = Review::query()
            ->where('status', ReviewStatus::Published)
            ->where('edit_window_ends_at', '<=', now())
            ->whereNull('locked_at')
            ->update([
                'locked_at' => now(),
                'status' => ReviewStatus::Locked,
            ]);

        $this->info("Locked {$count} review(s).");

        return self::SUCCESS;
    }
}
