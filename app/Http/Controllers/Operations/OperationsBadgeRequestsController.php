<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffBadgeRequest;
use App\Services\Operations\StaffBadgeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsBadgeRequestsController extends Controller
{
    public function __construct(private readonly StaffBadgeRequestService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/BadgeRequests/Index');
    }

    public function listing(): JsonResponse
    {
        return response()->json($this->service->listing());
    }

    public function detail(StaffBadgeRequest $badgeRequest): JsonResponse
    {
        return response()->json($this->service->detail($badgeRequest));
    }

    public function approve(Request $request, StaffBadgeRequest $badgeRequest): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->service->approve($badgeRequest, $request->user(), $data['note']);

        return response()->json(['message' => 'Badge awarded.']);
    }

    public function reject(Request $request, StaffBadgeRequest $badgeRequest): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->service->reject($badgeRequest, $request->user(), $data['note']);

        return response()->json(['message' => 'Request rejected. User notified.']);
    }

    public function escalate(Request $request, StaffBadgeRequest $badgeRequest): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->service->escalate($badgeRequest, $request->user(), $data['note']);

        return response()->json(['message' => 'Escalated to Super Admin.']);
    }
}
