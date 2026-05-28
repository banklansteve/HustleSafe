<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConversationMonitoringTermRequest;
use App\Http\Requests\Operations\ConversationMonitoringActionRequest;
use App\Models\ConversationMonitoringTerm;
use App\Models\ConversationSystematicEscalation;
use App\Models\ConversationThreadReview;
use App\Services\ConversationMonitoring\ConversationMonitoringAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminConversationMonitoringController extends Controller
{
    public function __construct(private readonly ConversationMonitoringAdminService $service) {}

    public function index(): Response
    {
        return Inertia::render('Admin/ConversationMonitoring/Index', [
            'summary' => $this->service->summary(),
            'isSuperAdmin' => true,
        ]);
    }

    public function moderationQueue(Request $request): JsonResponse
    {
        return response()->json($this->service->moderationQueue($request));
    }

    public function systematicQueue(Request $request): JsonResponse
    {
        return response()->json($this->service->systematicQueue($request));
    }

    public function threadDetail(ConversationThreadReview $review, Request $request): JsonResponse
    {
        $reveal = $request->boolean('reveal');

        return response()->json($this->service->threadDetail($review, revealFull: $reveal));
    }

    public function systematicDetail(ConversationSystematicEscalation $escalation): JsonResponse
    {
        return response()->json($this->service->systematicDetail($escalation));
    }

    public function dismiss(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->dismiss($review, $request->user(), (string) $data['reason']);

        return response()->json(['message' => 'Dismissed.']);
    }

    public function warn(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->service->warnUser($review, $request->user(), (string) $data['note']);

        return response()->json(['message' => 'Warning issued.']);
    }

    public function escalate(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $this->service->escalate($review, $request->user());

        return response()->json(['message' => 'Escalated.']);
    }

    public function flagRisk(ConversationThreadReview $review): JsonResponse
    {
        $this->service->flagForRiskUpdate($review);

        return response()->json(['message' => 'Risk recalculation queued.']);
    }

    public function resolveSystematic(Request $request, ConversationSystematicEscalation $escalation): JsonResponse
    {
        $data = $request->validate(['resolution_note' => ['required', 'string', 'max:2000']]);
        $this->service->resolveSystematic($escalation, $request->user(), (string) $data['resolution_note']);

        return response()->json(['message' => 'Systematic escalation resolved.']);
    }

    public function terms(): JsonResponse
    {
        return response()->json($this->service->termsPayload());
    }

    public function storeTerm(StoreConversationMonitoringTermRequest $request): JsonResponse
    {
        $term = $this->service->storeTerm($request->user(), $request->validated());

        return response()->json(['message' => 'Term saved.', 'term' => $term]);
    }

    public function updateTerm(StoreConversationMonitoringTermRequest $request, ConversationMonitoringTerm $term): JsonResponse
    {
        $updated = $this->service->updateTerm($term, $request->validated());

        return response()->json(['message' => 'Term updated.', 'term' => $updated]);
    }

    public function destroyTerm(ConversationMonitoringTerm $term): JsonResponse
    {
        $this->service->deleteTerm($term);

        return response()->json(['message' => 'Term removed.']);
    }
}
