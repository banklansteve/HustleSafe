<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformHealthService;
use Illuminate\Http\JsonResponse;

class AdminPlatformHealthController extends Controller
{
    public function __invoke(PlatformHealthService $health): JsonResponse
    {
        return response()->json($health->snapshot());
    }
}
