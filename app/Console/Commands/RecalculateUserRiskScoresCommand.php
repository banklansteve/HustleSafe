<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateUserRiskScoreJob;
use App\Models\User;
use Illuminate\Console\Command;

class RecalculateUserRiskScoresCommand extends Command
{
    protected $signature = 'risk:recalculate {--user= : Limit to a single user id}';

    protected $description = 'Queue composite risk score recalculation for users';

    public function handle(): int
    {
        $userId = $this->option('user');

        if ($userId) {
            RecalculateUserRiskScoreJob::dispatch((int) $userId);
            $this->info("Queued risk recalculation for user #{$userId}.");

            return self::SUCCESS;
        }

        $count = 0;
        User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['client', 'freelancer']))
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$count): void {
                foreach ($users as $user) {
                    RecalculateUserRiskScoreJob::dispatch($user->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} risk recalculation jobs.");

        return self::SUCCESS;
    }
}
