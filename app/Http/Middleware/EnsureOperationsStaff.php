<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform operations staff (role slug "admin"). Super admins use /admin exclusively.
 */
class EnsureOperationsStaff
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->user()?->role?->slug;
        if ($slug === 'super_admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($slug !== 'admin') {
            abort(403, __('This area is restricted to platform operations staff.'));
        }

        return $next($request);
    }
}
