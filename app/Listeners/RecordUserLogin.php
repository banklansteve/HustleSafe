<?php

namespace App\Listeners;

use App\Models\LoginEvent;
use Illuminate\Auth\Events\Login;
use Throwable;

class RecordUserLogin
{
    public function handle(Login $event): void
    {
        try {
            LoginEvent::query()->create([
                'user_id' => $event->user->getAuthIdentifier(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'logged_in_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
