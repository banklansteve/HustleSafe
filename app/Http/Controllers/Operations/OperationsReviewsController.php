<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\ModerationAppeal;
use App\Models\Review;
use App\Services\Operations\StaffReviewModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsReviewsController extends Controller
{
    public function __construct(private readonly StaffReviewModerationService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/Reviews/Index', [
            'queues' => $this->service->queues(),
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        $paginator = $this->service->listing($request);

        return response()->json([
            'items' => $paginator->items(),
            'meta' => ['total' => $paginator->total()],
        ]);
    }

    public function detail(Review $review): JsonResponse
    {
        return response()->json($this->service->detail($review));
    }

    public function approve(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->approveWithEdits($review, $request->user(), $data, $request);

        return response()->json(['message' => 'Review approved and published.']);
    }

    public function remove(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        $this->service->remove($review, $request->user(), $data, $request);

        return response()->json(['message' => 'Review removed.']);
    }

    public function requestRevision(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        $this->service->requestRevision($review, $request->user(), $data, $request);

        return response()->json(['message' => 'Revision requested from reviewer.']);
    }

    public function flag(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:8', 'max:2000'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
        ]);

        $this->service->flag($review, $request->user(), $data, $request);

        return response()->json(['message' => 'Review flagged for investigation.']);
    }

    public function resolveAppeal(Request $request, ModerationAppeal $appeal): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', 'in:uphold,overturn'],
            'note' => ['required', 'string', 'min:8', 'max:2000'],
        ]);

        $this->service->resolveAppeal($appeal, $request->user(), $data, $request);

        return response()->json(['message' => 'Appeal resolved.']);
    }
}
