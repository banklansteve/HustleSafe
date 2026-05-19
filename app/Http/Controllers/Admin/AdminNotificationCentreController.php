<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\Admin\AdminCommandCentreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminNotificationCentreController extends Controller
{
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

    public function action(AdminNotification $notification): RedirectResponse
    {
        $notification->forceFill(['read_at' => now(), 'actioned_at' => now()])->save();

        return $notification->action_url
            ? redirect($notification->action_url)->with('success', 'Notification actioned.')
            : back()->with('success', 'Notification actioned.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        AdminNotification::query()
            ->where(fn ($q) => $q->whereNull('admin_user_id')->orWhere('admin_user_id', $request->user()?->id))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
