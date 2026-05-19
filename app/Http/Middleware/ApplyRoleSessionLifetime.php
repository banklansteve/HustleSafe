<?php

namespace App\Http\Middleware;

use App\Support\RoleSessionLifetime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRoleSessionLifetime
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $user->loadMissing('role');
            RoleSessionLifetime::applyForRole($user->role?->slug);
        }

        return $next($request);
    }
}
