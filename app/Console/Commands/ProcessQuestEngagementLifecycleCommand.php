<?php

namespace App\Console\Commands;

use App\Services\QuestEngagementLifecycleService;
use Illuminate\Console\Command;

class ProcessQuestEngagementLifecycleCommand extends Command
{
    protected $signature = 'quests:process-lifecycle';

    protected $description = 'Send escrow engagement / post-deadline reminder emails and apply silent auto-completion when eligible';

    public function handle(QuestEngagementLifecycleService $service): int
    {
        $result = $service->run();
        $this->info('Lifecycle emails sent: '.$result['emails_sent']);
        $this->info('Quests auto-completed: '.$result['auto_completed']);

        return self::SUCCESS;
    }
}
