<?php

namespace App\Console\Commands;

use App\Services\Quest\QuestAutoNudgeService;
use App\Services\Quest\QuestHealthScoreService;
use Illuminate\Console\Command;

class ProcessQuestEngagementCommand extends Command
{
    protected $signature = 'quests:process-engagement';

    protected $description = 'Refresh quest health scores and send automated client/freelancer nudges';

    public function handle(QuestHealthScoreService $health, QuestAutoNudgeService $nudges): int
    {
        $refreshed = $health->refreshActiveEngagements();
        $sent = $nudges->run();

        $this->info(sprintf(
            'Refreshed %d health scores. Nudges sent: proposals=%d, escrow=%d, delivery=%d, shortlist=%d',
            $refreshed,
            $sent['proposals_no_client_login'] ?? 0,
            $sent['awarded_no_escrow'] ?? 0,
            $sent['delivery_pending_client_action'] ?? 0,
            $sent['shortlist_ready_no_award'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
