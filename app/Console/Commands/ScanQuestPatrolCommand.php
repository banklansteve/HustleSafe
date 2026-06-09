<?php

namespace App\Console\Commands;

use App\Services\Admin\QuestPatrol\QuestPatrolAnomalyService;
use Illuminate\Console\Command;

class ScanQuestPatrolCommand extends Command
{
    protected $signature = 'quest-patrol:scan';

    protected $description = 'Scan quests and proposals for patrol anomalies';

    public function handle(QuestPatrolAnomalyService $service): int
    {
        $created = $service->scanAll();
        $this->info("Quest patrol scan complete. {$created} new flag(s) created.");

        return self::SUCCESS;
    }
}
