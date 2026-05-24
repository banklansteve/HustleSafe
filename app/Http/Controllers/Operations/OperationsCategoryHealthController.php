<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\StaffCategoryHealthService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OperationsCategoryHealthController extends Controller
{
    public function __construct(private readonly StaffCategoryHealthService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/CategoryHealth/Index');
    }

    public function dashboard(): JsonResponse
    {
        return response()->json($this->service->dashboard());
    }
}
