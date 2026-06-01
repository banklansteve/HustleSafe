<?php

namespace App\Console\Commands;

use App\Services\Admin\QuestBoostService;
use Illuminate\Console\Command;

class ExpireQuestBoostsCommand extends Command
{
    protected $signature = 'quest-boosts:expire';

    protected $description = 'Expire quest boosts that have passed their end time';

    public function handle(QuestBoostService $service): int
    {
        $count = $service->expireDueBoosts();
        $this->info("Expired {$count} quest boost(s).");

        return self::SUCCESS;
    }
}
