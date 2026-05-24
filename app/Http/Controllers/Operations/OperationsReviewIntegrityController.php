<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffReviewIntegrityCase;
use App\Services\Operations\StaffReviewIntegrityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsReviewIntegrityController extends Controller
{
    public function __construct(private readonly StaffReviewIntegrityService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/ReviewIntegrity/Index');
    }

    public function listing(): JsonResponse
    {
        return response()->json($this->service->listing());
    }

    public function detail(StaffReviewIntegrityCase $case): JsonResponse
    {
        return response()->json($this->service->detail($case));
    }

    public function openCase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pattern_type' => ['required', 'string'],
            'pattern_key' => ['required', 'string'],
            'subject_user_id' => ['nullable', 'integer'],
            'pattern_data' => ['nullable', 'array'],
        ]);

        $case = $this->service->openCase($request->user(), $data);

        return response()->json(['message' => 'Case opened.', 'case_id' => $case->id]);
    }

    public function saveFindings(Request $request, StaffReviewIntegrityCase $case): JsonResponse
    {
        $data = $request->validate([
            'findings' => ['required', 'string', 'max:5000'],
            'flagged_review_ids' => ['nullable', 'array'],
        ]);

        $this->service->saveFindings($case, $request->user(), $data['findings'], $data['flagged_review_ids'] ?? null);

        return response()->json(['message' => 'Findings saved.']);
    }

    public function bulkFlag(Request $request, StaffReviewIntegrityCase $case): JsonResponse
    {
        $data = $request->validate(['review_ids' => ['required', 'array', 'min:1']]);
        $this->service->bulkFlag($case, $request->user(), $data['review_ids']);

        return response()->json(['message' => 'Reviews flagged for Super Admin confirmation.']);
    }

    public function escalate(Request $request, StaffReviewIntegrityCase $case): JsonResponse
    {
        $this->service->escalate($case, $request->user());

        return response()->json(['message' => 'Escalated to Super Admin.']);
    }
}
