<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Operational staff (role slug "admin") use /operations — keep /admin for super admins only.
 */
class RedirectOperationsStaffFromAdminConsole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role?->slug === 'admin') {
            return redirect()->route('operations.dashboard');
        }

        return $next($request);
    }
}
