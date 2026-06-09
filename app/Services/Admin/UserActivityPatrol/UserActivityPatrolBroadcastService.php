<?php

namespace App\Services\Admin\UserActivityPatrol;

use App\Events\UserActivityPatrolChanged;

final class UserActivityPatrolBroadcastService
{
    public function dispatch(string $action = 'updated', ?int $userId = null, ?int $flagId = null): void
    {
        UserActivityPatrolChanged::dispatch($action, $userId, $flagId);
    }
}
