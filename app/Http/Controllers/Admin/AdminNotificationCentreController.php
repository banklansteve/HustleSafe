<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\Admin\AdminCommandCentreService;
use App\Services\Operations\StaffNotificationCentreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminNotificationCentreController extends Controller
{
    public function __construct(private readonly StaffNotificationCentreService $notifications) {}

    public function index(Request $request, AdminCommandCentreService $service): Response
    {
        return Inertia::render('Admin/CommandRisk/Index', [
            'mode' => 'notifications',
            'payload' => $service->notificationPayload($request->user()),
        ]);
    }

    public function markRead(AdminNotification $notification): RedirectResponse
    {
        $notification->forceFill(['read_at' => now()])->save();

        return back()->with('success', 'Notification marked as read.');
    }

    public function action(Request $request, AdminNotification $notification): RedirectResponse
    {
        $notification->forceFill(['actioned_at' => now()])->save();

        return $this->open($request, $notification);
    }

    public function open(Request $request, AdminNotification $notification): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $notification->admin_user_id === null || (int) $notification->admin_user_id === (int) $user?->id,
            403,
        );

        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        $target = $this->notifications->resolvedActionUrl($notification, $user);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $target]);
        }

        return redirect($target);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        AdminNotification::query()
            ->where(fn ($q) => $q->whereNull('admin_user_id')->orWhere('admin_user_id', $request->user()?->id))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function unreadCount(Request $request): \Illuminate\Http\JsonResponse
    {
        $adminId = $request->user()?->id;

        $count = AdminNotification::query()
            ->where(fn ($q) => $q->whereNull('admin_user_id')->orWhere('admin_user_id', $adminId))
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }
}
