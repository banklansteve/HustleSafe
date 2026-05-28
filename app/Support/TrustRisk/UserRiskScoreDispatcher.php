<?php

namespace App\Support\TrustRisk;

use App\Jobs\RecalculateUserRiskScoreJob;

class UserRiskScoreDispatcher
{
    public static function dispatch(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        RecalculateUserRiskScoreJob::dispatch($userId)->afterResponse();
    }

    /**
     * @param  iterable<int>  $userIds
     */
    public static function dispatchMany(iterable $userIds): void
    {
        foreach ($userIds as $id) {
            self::dispatch((int) $id);
        }
    }
}
