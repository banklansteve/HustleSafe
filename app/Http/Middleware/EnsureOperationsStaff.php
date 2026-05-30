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
            $routeName = (string) ($request->route()?->getName() ?? '');
            $allowedForSuperAdmin = str_starts_with($routeName, 'operations.moderation')
                || str_starts_with($routeName, 'operations.api.moderation')
                || str_starts_with($routeName, 'operations.support-tickets')
                || str_starts_with($routeName, 'operations.trust')
                || str_starts_with($routeName, 'operations.api.trust')
                || str_starts_with($routeName, 'operations.conversation-monitoring')
                || str_starts_with($routeName, 'operations.api.conversation-monitoring');

            if ($allowedForSuperAdmin) {
                return $next($request);
            }

            return redirect()->route('admin.dashboard');
        }

        if ($slug !== 'admin') {
            abort(403, __('This area is restricted to platform operations staff.'));
        }

        return $next($request);
    }
}
