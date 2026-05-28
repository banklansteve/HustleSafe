<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\ModerationAppeal;
use App\Models\Review;
use App\Models\ReviewModerationCluster;
use App\Models\User;
use App\Services\Operations\StaffReviewModerationService;
use App\Services\ReviewModeration\ReviewManipulationReportService;
use App\Services\ReviewModeration\ReviewModerationAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsReviewsController extends Controller
{
    public function __construct(
        private readonly StaffReviewModerationService $service,
        private readonly ReviewModerationAdminService $moderation,
        private readonly ReviewManipulationReportService $manipulation,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Operations/Reviews/Index', [
            'queues' => $this->moderation->queues(),
            'canExportManipulation' => $request->user()?->role?->slug === 'super_admin',
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        $queue = (string) $request->input('queue', 'authenticity');

        if ($queue === 'manipulation') {
            return response()->json($this->moderation->manipulationDashboard());
        }

        $paginator = $this->moderation->listing($request);

        return response()->json([
            'items' => $paginator->items(),
            'meta' => ['total' => $paginator->total()],
        ]);
    }

    public function detail(Review $review): JsonResponse
    {
        return response()->json($this->moderation->detail($review));
    }

    public function clusterDetail(ReviewModerationCluster $cluster): JsonResponse
    {
        return response()->json($this->moderation->clusterDetail($cluster));
    }

    public function manipulationBreakdown(int $freelancer): JsonResponse
    {
        return response()->json($this->moderation->freelancerBreakdown($freelancer));
    }

    public function exportManipulation(Request $request, string $reportType): StreamedResponse|JsonResponse
    {
        if ($request->user()?->role?->slug !== 'super_admin') {
            return response()->json(['message' => 'Export is limited to Super Admins.'], 403);
        }

        if (! in_array($reportType, ['freelancer_concentration', 'client_pattern'], true)) {
            return response()->json(['message' => 'Unknown report type.'], 422);
        }

        return $this->manipulation->exportCsv($reportType);
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

    public function requestAmendment(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'instructions' => ['required', 'string', 'min:12', 'max:2000'],
            'required_changes' => ['nullable', 'array'],
            'required_changes.*' => ['string', 'max:200'],
        ]);

        $this->moderation->issueAmendment($review, $request->user(), $data, $request);

        return response()->json(['message' => 'Amendment request sent to reviewer.']);
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
