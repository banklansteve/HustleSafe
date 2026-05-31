<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\ConversationSystematicEscalation;
use App\Models\ConversationThreadReview;
use App\Models\User;
use App\Services\ConversationMonitoring\ConversationMonitoringAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsConversationMonitoringController extends Controller
{
    public function __construct(private readonly ConversationMonitoringAdminService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Operations/ConversationMonitoring/Index', [
            'summary' => $this->service->summary($request->user()),
            'isSuperAdmin' => false,
            'openReviewId' => $request->integer('review') ?: null,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->service->summary($request->user()));
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
        return response()->json($this->service->threadDetail($review, revealFull: false, viewer: $request->user()));
    }

    public function systematicDetail(ConversationSystematicEscalation $escalation): JsonResponse
    {
        return response()->json($this->service->systematicDetail($escalation));
    }

    public function dismiss(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->dismiss($review, $request->user(), (string) $data['reason']);

        return response()->json(['message' => 'Flag dismissed as false positive.']);
    }

    public function warn(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'template_slug' => ['nullable', 'string', 'max:120'],
        ]);

        $this->service->warnUser(
            $review,
            $request->user(),
            (string) $data['note'],
            isset($data['target_user_id']) ? (int) $data['target_user_id'] : null,
            $data['template_slug'] ?? null,
        );

        return response()->json(['message' => 'Policy warning recorded.']);
    }

    public function escalateSuperAdmin(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:8', 'max:2000']]);
        $this->service->escalateToSuperAdmin($review, $request->user(), (string) $data['note']);

        return response()->json(['message' => 'Escalated to Super Admin for permanent ban review.']);
    }

    public function suspendUser(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = User::query()->findOrFail((int) $data['user_id']);
        $this->service->suspendUser($request->user(), $target, $review, $data['note'] ?? null);

        return response()->json(['message' => 'User suspended.']);
    }

    public function flagRisk(ConversationThreadReview $review): JsonResponse
    {
        $this->service->flagForRiskUpdate($review);

        return response()->json(['message' => 'Risk score recalculation queued for both parties.']);
    }

    public function dismissSystematic(Request $request, ConversationSystematicEscalation $escalation): JsonResponse
    {
        $this->service->attemptDismissSystematic($escalation, $request->user());

        return response()->json(['message' => 'Resolved.']);
    }
}
