<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreConversationMonitoringTermRequest;
use App\Models\ConversationMonitoringTerm;
use App\Models\ConversationSystematicEscalation;
use App\Models\ConversationThreadReview;
use App\Models\User;
use App\Services\ConversationMonitoring\ConversationMonitoringAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminConversationMonitoringController extends Controller
{
    public function __construct(private readonly ConversationMonitoringAdminService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/ConversationMonitoring/Index', [
            'summary' => $this->service->summary(),
            'isSuperAdmin' => true,
            'openReviewId' => $request->integer('review') ?: null,
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

        return response()->json($this->service->threadDetail($review, revealFull: $reveal, viewer: $request->user()));
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

        return response()->json(['message' => 'Warning issued.']);
    }

    public function assign(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $data = $request->validate(['staff_id' => ['required', 'integer', 'exists:users,id']]);
        $this->service->assignToStaff($review, $request->user(), (int) $data['staff_id']);

        return response()->json(['message' => 'Assigned to staff admin.']);
    }

    public function escalateSuperAdmin(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:8', 'max:2000']]);
        $this->service->escalateToSuperAdmin($review, $request->user(), (string) $data['note']);

        return response()->json(['message' => 'Escalated to Super Admin.']);
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

    public function banUser(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = User::query()->findOrFail((int) $data['user_id']);
        $this->service->banUser($request->user(), $target, $review, $data['note'] ?? null);

        return response()->json(['message' => 'User permanently banned.']);
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

    public function destroyReview(Request $request, ConversationThreadReview $review): JsonResponse
    {
        $this->service->deleteReview($review, $request->user());

        return response()->json(['message' => 'Review record deleted.']);
    }

    public function bulkDestroyReviews(Request $request): JsonResponse
    {
        $data = $request->validate([
            'review_ids' => ['required', 'array', 'min:1', 'max:100'],
            'review_ids.*' => ['integer', 'exists:conversation_thread_reviews,id'],
        ]);

        $count = $this->service->bulkDeleteReviews($data['review_ids'], $request->user());

        return response()->json(['message' => __('Deleted :count review record(s).', ['count' => $count]), 'deleted' => $count]);
    }
}
