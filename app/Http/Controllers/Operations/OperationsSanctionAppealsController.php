<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffSanctionAppeal;
use App\Services\Operations\StaffSanctionAppealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsSanctionAppealsController extends Controller
{
    public function __construct(private readonly StaffSanctionAppealService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/SanctionAppeals/Index');
    }

    public function listing(): JsonResponse
    {
        return response()->json($this->service->listing());
    }

    public function detail(StaffSanctionAppeal $appeal): JsonResponse
    {
        return response()->json($this->service->detail($appeal));
    }

    public function approve(Request $request, StaffSanctionAppeal $appeal): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:3000']]);
        $this->service->approve($appeal, $request->user(), $data['note']);

        return response()->json(['message' => 'Appeal approved and sanction lifted.']);
    }

    public function reject(Request $request, StaffSanctionAppeal $appeal): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:3000']]);
        $this->service->reject($appeal, $request->user(), $data['note']);

        return response()->json(['message' => 'Appeal rejected. User notified.']);
    }

    public function escalate(Request $request, StaffSanctionAppeal $appeal): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:3000']]);
        $this->service->escalate($appeal, $request->user(), $data['note']);

        return response()->json(['message' => 'Escalated to Super Admin.']);
    }
}
