<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\StaffCommunicationsViewerService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OperationsCommunicationsViewerController extends Controller
{
    public function __construct(private readonly StaffCommunicationsViewerService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/CommunicationsViewer/Index');
    }

    public function listing(): JsonResponse
    {
        return response()->json($this->service->listing());
    }
}
