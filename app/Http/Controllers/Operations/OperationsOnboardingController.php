<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffOnboardingAssistanceRecord;
use App\Services\Operations\StaffOnboardingAssistanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsOnboardingController extends Controller
{
    public function __construct(private readonly StaffOnboardingAssistanceService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/Onboarding/Index');
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->service->listing($request));
    }

    public function detail(StaffOnboardingAssistanceRecord $record): JsonResponse
    {
        return response()->json($this->service->detail($record));
    }

    public function outreach(Request $request, StaffOnboardingAssistanceRecord $record): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'channel' => ['nullable', 'in:both,email,in_app'],
        ]);

        $this->service->outreach($request->user(), $record, $data, $request);

        return response()->json(['message' => 'Outreach sent to user.']);
    }

    public function resolve(Request $request, StaffOnboardingAssistanceRecord $record): JsonResponse
    {
        $this->service->resolve($request->user(), $record, $request);

        return response()->json(['message' => 'Marked as resolved.']);
    }

    public function createTicket(Request $request, StaffOnboardingAssistanceRecord $record): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:3000'],
            'priority' => ['nullable', 'in:low,medium,high'],
        ]);

        $this->service->createTicket($request->user(), $record, $data, $request);

        return response()->json(['message' => 'Support ticket created.']);
    }
}
