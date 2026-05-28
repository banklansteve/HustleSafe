<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Onboarding\OnboardingQualityControlService;
use Illuminate\Console\Command;

class SyncOnboardingQualityReviewsCommand extends Command
{
    protected $signature = 'onboarding-quality:sync {--hours=48 : Signup window in hours}';

    protected $description = 'Create or refresh onboarding quality reviews for recent client/freelancer signups.';

    public function handle(OnboardingQualityControlService $service): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $since = now()->subHours($hours);

        $count = 0;
        User::query()
            ->where('created_at', '>=', $since)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['client', 'freelancer']))
            ->orderBy('id')
            ->each(function (User $user) use ($service, &$count): void {
                if ($service->ensureReviewFor($user) !== null) {
                    $service->syncEvaluation($user);
                    $count++;
                }
            });

        $this->info("Synced {$count} onboarding quality review(s).");

        return self::SUCCESS;
    }
}
