<?php

namespace App\Console\Commands;

use App\Services\Operations\OnboardingTrackingEngine;
use Illuminate\Console\Command;

class ProcessOnboardingAssistanceCommand extends Command
{
    protected $signature = 'operations:process-onboarding-assistance';

    protected $description = 'Evaluate client and freelancer onboarding drop-offs and update assistance records.';

    public function handle(OnboardingTrackingEngine $engine): int
    {
        $updated = $engine->runDailyEvaluation();
        $this->info("Onboarding assistance evaluation complete. {$updated} record(s) created or updated.");

        return self::SUCCESS;
    }
}
