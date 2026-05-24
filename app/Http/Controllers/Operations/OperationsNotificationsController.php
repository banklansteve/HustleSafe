<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\Operations\StaffNotificationCentreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsNotificationsController extends Controller
{
    public function __construct(private readonly StaffNotificationCentreService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/Notifications/Index');
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->service->listing($request->user(), $request));
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json($this->service->preferences($request->user()));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate(['preferences' => ['required', 'array']]);
        $this->service->updatePreferences($request->user(), $data);

        return response()->json(['message' => 'Notification preferences saved.']);
    }

    public function markRead(Request $request, AdminNotification $notification): JsonResponse
    {
        $this->service->markRead($notification, $request->user());

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markActioned(Request $request, AdminNotification $notification): JsonResponse
    {
        $this->service->markActioned($notification, $request->user());

        return response()->json(['message' => 'Notification actioned.']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->service->unreadCount($request->user())]);
    }

    public function open(Request $request, AdminNotification $notification): RedirectResponse|JsonResponse
    {
        $staff = $request->user();
        abort_unless((int) $notification->admin_user_id === (int) $staff->id, 403);

        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        $target = $this->service->resolvedActionUrl($notification, $staff);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $target]);
        }

        return redirect($target);
    }
}
