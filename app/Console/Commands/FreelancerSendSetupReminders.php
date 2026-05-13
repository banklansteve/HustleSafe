<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\FreelancerSetupReminderNotification;
use App\Services\FreelancerWorkspaceReadinessService;
use Illuminate\Console\Command;

class FreelancerSendSetupReminders extends Command
{
    protected $signature = 'freelancers:send-setup-reminders';

    protected $description = 'Notify freelancers (email + in-app) to complete categories, address, or ID verification.';

    public function handle(FreelancerWorkspaceReadinessService $readiness): int
    {
        $hours = (int) config('freelancer_workspace.setup_reminder_interval_hours', 48);

        User::query()
            ->whereRelation('role', 'slug', 'freelancer')
            ->where(function ($q) use ($hours): void {
                $q->whereNull('freelancer_last_setup_reminder_at')
                    ->orWhere('freelancer_last_setup_reminder_at', '<=', now()->subHours($hours));
            })
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($readiness): void {
                foreach ($users as $user) {
                    $summary = $readiness->summarize($user);
                    if (! ($summary['reminder_worthy'] ?? false)) {
                        continue;
                    }
                    $user->notify(new FreelancerSetupReminderNotification($summary));
                    $user->forceFill(['freelancer_last_setup_reminder_at' => now()])->save();
                }
            });

        $this->info('Freelancer setup reminder pass complete.');

        return self::SUCCESS;
    }
}
