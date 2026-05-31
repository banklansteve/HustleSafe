<?php

namespace App\Console\Commands;

use App\Services\Quest\QuestListingExpiryService;
use Illuminate\Console\Command;

class ExpireQuestListingsCommand extends Command
{
    protected $signature = 'quests:expire-listings';

    protected $description = 'Send proposal deadline warnings and close unawarded open quests past listing expiry.';

    public function handle(QuestListingExpiryService $expiry): int
    {
        $warnings = $expiry->sendDeadlineWarnings();
        $expired = $expiry->expireDueListings();

        $this->info("Sent {$warnings} deadline warning(s); closed {$expired} unawarded quest(s).");

        return self::SUCCESS;
    }
}
