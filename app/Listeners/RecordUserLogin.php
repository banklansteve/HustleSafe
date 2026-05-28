<?php

namespace App\Listeners;

use App\Models\LoginEvent;
use App\Support\TrustRisk\UserRiskScoreDispatcher;
use Illuminate\Auth\Events\Login;
use Throwable;

class RecordUserLogin
{
    public function handle(Login $event): void
    {
        try {
            $userId = (int) $event->user->getAuthIdentifier();
            LoginEvent::query()->create([
                'user_id' => $userId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'logged_in_at' => now(),
            ]);
            UserRiskScoreDispatcher::dispatch($userId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
