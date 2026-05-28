<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TrustRisk\UserRiskMonitoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateUserRiskScoreJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $userId) {}

    public function handle(UserRiskMonitoringService $monitoring): void
    {
        $user = User::query()->find($this->userId);
        if ($user === null) {
            return;
        }

        $monitoring->recalculateAndPersist($user);
    }
}
