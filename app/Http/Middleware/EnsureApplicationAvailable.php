<?php

namespace App\Http\Middleware;

use App\Services\Admin\MaintenanceModeService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationAvailable
{
    public function __construct(private readonly MaintenanceModeService $maintenance) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Never block the maintenance control API — otherwise "turn off" cannot reach the server.
        if ($this->isMaintenanceControlRoute($request)) {
            return $next($request);
        }

        if (! $this->maintenance->isEnabled()) {
            return $next($request);
        }

        if ($this->canBypass($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->maintenance->message(),
            ], 503);
        }

        return Inertia::render('Errors/Maintenance', [
            'message' => $this->maintenance->message(),
            'returnTime' => $this->maintenance->returnTime(),
        ])->toResponse($request)->setStatusCode(503);
    }

    private function isMaintenanceControlRoute(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return str_starts_with($path, 'admin/api/maintenance');
    }

    private function canBypass(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if (in_array($path, ['up', 'login', 'logout', 'register', 'forgot-password'], true)) {
            return true;
        }

        $role = $request->user()?->role?->slug;

        if (str_starts_with($path, 'admin')) {
            return $role === 'super_admin';
        }

        if (str_starts_with($path, 'operations')) {
            return in_array($role, ['admin', 'super_admin'], true);
        }

        return false;
    }
}
