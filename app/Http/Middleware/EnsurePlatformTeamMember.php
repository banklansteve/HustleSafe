<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformTeamMember
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->user()?->role?->slug;

        if (! in_array($slug, ['admin', 'super_admin'], true)) {
            abort(403, __('This area is restricted to platform team members.'));
        }

        return $next($request);
    }
}
