<?php

namespace App\Console\Commands;

use App\Services\Matching\FreelancerMetricsService;
use Illuminate\Console\Command;

class RefreshFreelancerMetricsCommand extends Command
{
    protected $signature = 'freelancer-metrics:refresh {--user= : Refresh a single user id}';

    protected $description = 'Refresh pre-computed freelancer matching metrics';

    public function handle(FreelancerMetricsService $service): int
    {
        $userId = $this->option('user');
        if ($userId !== null) {
            $user = \App\Models\User::query()->findOrFail((int) $userId);
            $service->refresh($user);
            $this->info("Refreshed metrics for user #{$user->id}.");

            return self::SUCCESS;
        }

        $count = $service->refreshAll();
        $this->info("Refreshed metrics for {$count} freelancers.");

        return self::SUCCESS;
    }
}
