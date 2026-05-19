<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminCommandCentreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommandController extends Controller
{
    public function search(Request $request, AdminCommandCentreService $service): JsonResponse
    {
        return response()->json($service->search((string) $request->query('q', '')));
    }
}
