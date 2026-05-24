<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMaintenanceRequest;
use App\Services\Admin\MaintenanceModeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminMaintenanceController extends Controller
{
    public function status(MaintenanceModeService $maintenance): JsonResponse
    {
        return response()->json($maintenance->status());
    }

    public function on(UpdateMaintenanceRequest $request, MaintenanceModeService $maintenance): JsonResponse
    {
        $maintenance->enable($request->input('message'), $request->input('return_time'));

        return $this->respond($maintenance, true);
    }

    public function off(Request $request, MaintenanceModeService $maintenance): JsonResponse|RedirectResponse
    {
        $maintenance->disable();

        if ($request->expectsJson()) {
            return $this->respond($maintenance, false);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Maintenance mode is OFF — site is live.');
    }

    /**
     * Legacy PATCH handler — accepts enabled flag or action=off|on.
     */
    public function update(Request $request, MaintenanceModeService $maintenance): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $action = strtolower((string) $request->input('action', ''));
        if ($action === 'off' || $request->input('enabled') === 0 || $request->input('enabled') === '0') {
            $maintenance->disable();

            return $this->respond($maintenance, false);
        }

        if ($action === 'on' || $request->boolean('enabled')) {
            $request->validate([
                'message' => ['nullable', 'string', 'max:500'],
                'return_time' => ['nullable', 'string', 'max:120'],
            ]);
            $maintenance->enable($request->input('message'), $request->input('return_time'));

            return $this->respond($maintenance, true);
        }

        $maintenance->disable();

        return $this->respond($maintenance, false);
    }

    private function respond(MaintenanceModeService $maintenance, bool $enabled): JsonResponse
    {
        return response()->json([
            'message' => $enabled
                ? 'Maintenance mode is ON — users see the workshop page.'
                : 'Maintenance mode is OFF — site is live.',
            'status' => $maintenance->status(),
        ]);
    }
}
