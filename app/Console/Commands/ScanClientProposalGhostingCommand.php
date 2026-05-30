<?php

namespace App\Console\Commands;

use App\Services\Proposals\ProposalTrustBehaviourService;
use Illuminate\Console\Command;

class ScanClientProposalGhostingCommand extends Command
{
    protected $signature = 'proposals:scan-client-ghosting';

    protected $description = 'Flag clients who leave open quests with ignored proposals';

    public function handle(ProposalTrustBehaviourService $trust): int
    {
        $count = $trust->scanOpenQuestsForClientGhosting();
        $this->info("Recorded {$count} new client proposal-ghosting events.");

        return self::SUCCESS;
    }
}
