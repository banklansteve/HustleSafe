<?php

namespace App\Http\Middleware;

use App\Support\RoleSessionLifetime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->role?->slug !== 'super_admin') {
            abort(403, __('This area is restricted to platform super administrators.'));
        }

        RoleSessionLifetime::applyForRole('super_admin');

        return $next($request);
    }
}
