<?php

namespace App\Http\Controllers;

use App\Services\UserNotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationNavController extends Controller
{
    public function __invoke(Request $request, UserNotificationPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if ($user->role?->slug === 'admin') {
            return response()->json([
                'recentNotifications' => [],
                'unreadNotificationsCount' => 0,
            ]);
        }

        return response()->json([
            'recentNotifications' => $presenter->recentForNav($user, 8),
            'unreadNotificationsCount' => $presenter->groupedUnreadCount($user),
        ]);
    }
}
