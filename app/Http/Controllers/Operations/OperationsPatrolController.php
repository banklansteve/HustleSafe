<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffPatrolItem;
use App\Models\StaffPatrolSession;
use App\Services\Operations\StaffContentPatrolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsPatrolController extends Controller
{
    public function __construct(private readonly StaffContentPatrolService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/Patrol/Index');
    }

    public function sessions(Request $request): JsonResponse
    {
        return response()->json($this->service->sessions($request->user()));
    }

    public function categories(): JsonResponse
    {
        return response()->json($this->service->categories());
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_type' => ['required', 'in:quests,proposals'],
            'category_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sample_size' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $session = $this->service->startSession($request->user(), $data);

        return response()->json(['message' => 'Patrol session started.', 'session_id' => $session->id]);
    }

    public function sessionDetail(Request $request, StaffPatrolSession $session): JsonResponse
    {
        return response()->json($this->service->sessionDetail($session, $request->user()));
    }

    public function decide(Request $request, StaffPatrolItem $item): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:clear,flag,contact,escalate'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->decide($item, $request->user(), $data, $request);

        return response()->json(['message' => 'Patrol decision saved.']);
    }
}
