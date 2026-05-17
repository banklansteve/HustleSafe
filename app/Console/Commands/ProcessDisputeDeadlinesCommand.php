<?php

namespace App\Console\Commands;

use App\Services\Disputes\QuestDisputeWorkflowService;
use Illuminate\Console\Command;

class ProcessDisputeDeadlinesCommand extends Command
{
    protected $signature = 'disputes:process-deadlines';

    protected $description = 'Escalate or auto-resolve disputes when configured timers expire';

    public function handle(QuestDisputeWorkflowService $workflow): int
    {
        $n = $workflow->processDeadlines();
        $this->info("Processed {$n} dispute deadline(s).");

        return self::SUCCESS;
    }
}
