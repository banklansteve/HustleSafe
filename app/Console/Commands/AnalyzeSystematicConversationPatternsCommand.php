<?php

namespace App\Console\Commands;

use App\Services\ConversationMonitoring\ConversationSystematicPatternService;
use Illuminate\Console\Command;

class AnalyzeSystematicConversationPatternsCommand extends Command
{
    protected $signature = 'conversation-monitoring:analyze-systematic';

    protected $description = 'Detect systematic off-platform conversation patterns across users';

    public function handle(ConversationSystematicPatternService $service): int
    {
        $count = $service->analyze();
        $this->info("Created {$count} systematic escalation(s).");

        return self::SUCCESS;
    }
}
