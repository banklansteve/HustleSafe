<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationClearController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $scope = (string) $request->input('scope', 'all');

        $query = $user->notifications();

        if ($scope === 'read') {
            $query->whereNotNull('read_at');
        }

        $deleted = $query->delete();

        return response()->json([
            'deleted' => $deleted,
            'recentNotifications' => [],
            'unreadNotificationsCount' => 0,
        ]);
    }
}
