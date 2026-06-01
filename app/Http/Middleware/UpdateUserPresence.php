<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->routeIs('logout')) {
            return;
        }

        $user = $request->user();
        if ($user === null) {
            return;
        }

        $key = 'presence_touch:'.$user->id;
        if (Cache::has($key)) {
            return;
        }

        Cache::put($key, true, now()->addMinutes(2));
        $user->forceFill(['last_active_at' => now()])->saveQuietly();
    }
}
