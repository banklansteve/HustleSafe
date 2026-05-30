<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformFinancialHealthService;
use Illuminate\Http\JsonResponse;

class AdminPlatformFinancialHealthController extends Controller
{
    public function __invoke(PlatformFinancialHealthService $finance): JsonResponse
    {
        abort_unless(request()->user()?->role?->slug === 'super_admin', 403);

        return response()->json($finance->snapshot());
    }
}
