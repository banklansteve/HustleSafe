<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRiskNetworkNoteRequest;
use App\Http\Requests\Operations\StoreTrustWatchlistRequest;
use App\Models\StaffWatchlistItem;
use App\Models\User;
use App\Services\Operations\StaffTrustWatchlistService;
use App\Services\TrustRisk\TrustRiskSettingsService;
use App\Services\TrustRisk\UserRiskMonitoringService;
use App\Services\TrustRisk\UserRiskNetworkGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTrustRiskController extends Controller
{
    public function __construct(
        private readonly UserRiskMonitoringService $riskMonitoring,
        private readonly StaffTrustWatchlistService $watchlist,
        private readonly UserRiskNetworkGraphService $networkGraph,
        private readonly TrustRiskSettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/TrustRisk/Index', [
            'initialUserId' => $request->integer('user') ?: null,
            'thresholds' => $this->settings->thresholds(),
            'queueCount' => $this->riskMonitoring->queueCount(),
        ]);
    }

    public function riskQueue(Request $request): JsonResponse
    {
        return response()->json($this->riskMonitoring->riskQueue($request));
    }

    public function allWatchlists(Request $request): JsonResponse
    {
        return response()->json($this->watchlist->watchlist($request->user(), true));
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json($this->watchlist->feed($request->user(), true));
    }

    public function userRisk(User $user): JsonResponse
    {
        return response()->json($this->riskMonitoring->userDetail($user));
    }

    public function networkGraph(User $user): JsonResponse
    {
        return response()->json($this->networkGraph->graphForUser($user));
    }

    public function networkNotes(User $user): JsonResponse
    {
        return response()->json(['notes' => $this->networkGraph->notesForUser($user)]);
    }

    public function storeNetworkNote(StoreRiskNetworkNoteRequest $request, User $user): JsonResponse
    {
        $note = $this->networkGraph->storeNote($user, $request->user(), $request->validated('note'));

        return response()->json([
            'message' => 'Investigation note saved.',
            'note' => [
                'id' => $note->id,
                'note' => $note->note,
                'author' => $request->user()->name,
                'created_at' => $note->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function storeWatchlist(StoreTrustWatchlistRequest $request): JsonResponse
    {
        $item = $this->watchlist->addToWatchlist($request->user(), [
            'watchable_type' => User::class,
            'watchable_id' => $request->integer('user_id'),
            'label' => $request->input('label'),
            'reason' => $request->input('reason'),
            'notes' => $request->input('notes'),
            'review_by_date' => $request->input('review_by_date'),
            'severity' => $request->input('severity'),
            'visibility' => $request->input('visibility'),
        ]);

        return response()->json(['message' => 'Added to watchlist.', 'item' => $item->id]);
    }

    public function destroyWatchlist(Request $request, StaffWatchlistItem $item): JsonResponse
    {
        $this->watchlist->removeFromWatchlist($item, $request->user(), true);

        return response()->json(['message' => 'Removed from watchlist.']);
    }
}
