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
            $ip = request()->ip();
            $agent = request()->userAgent();

            $duplicate = LoginEvent::query()
                ->where('user_id', $userId)
                ->where('logged_in_at', '>=', now()->subMinutes(2))
                ->where('ip_address', $ip)
                ->when($agent !== null, fn ($q) => $q->where('user_agent', $agent))
                ->exists();

            if ($duplicate) {
                UserRiskScoreDispatcher::dispatch($userId);

                return;
            }

            LoginEvent::query()->create([
                'user_id' => $userId,
                'ip_address' => $ip,
                'user_agent' => $agent,
                'logged_in_at' => now(),
            ]);
            UserRiskScoreDispatcher::dispatch($userId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
