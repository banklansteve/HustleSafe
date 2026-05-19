<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdvancedUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserProfileTabController extends Controller
{
    public function __invoke(User $user, Request $request, AdvancedUserManagementService $service): JsonResponse
    {
        $tab = (string) $request->query('tab', 'overview');

        return response()->json($service->profile($user, $tab));
    }
}
