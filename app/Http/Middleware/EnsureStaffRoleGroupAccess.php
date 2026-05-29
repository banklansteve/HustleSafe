<?php

namespace App\Http\Middleware;

use App\Services\Hr\StaffRoleAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRoleGroupAccess
{
    public function __construct(private readonly StaffRoleAccessService $accessService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->role?->slug !== 'admin') {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($this->accessService->canAccessRoute($user, $routeName)) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        abort(403, __('Your active role-group assignment does not permit this action.'));
    }
}
