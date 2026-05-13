<?php

namespace App\Console\Commands;

use App\Enums\QuestStatus;
use App\Models\Quest;
use Illuminate\Console\Command;

class ExpireQuestListingsCommand extends Command
{
    protected $signature = 'quests:expire-listings';

    protected $description = 'Close open quests whose listing expiry time has passed.';

    public function handle(): int
    {
        $count = Quest::query()
            ->where('status', QuestStatus::Open)
            ->whereNull('freelancer_id')
            ->whereNotNull('listing_expires_at')
            ->where('listing_expires_at', '<=', now())
            ->update([
                'status' => QuestStatus::Closed,
                'closure_type' => 'listing_expired',
            ]);

        $this->info("Expired {$count} quest(s).");

        return self::SUCCESS;
    }
}
