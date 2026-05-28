<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreTrustWatchlistRequest;
use App\Models\StaffWatchlistItem;
use App\Models\User;
use App\Services\Operations\StaffTrustMonitoringService;
use App\Services\Operations\StaffTrustWatchlistService;
use App\Services\TrustRisk\UserRiskMonitoringService;
use App\Services\TrustRisk\UserRiskNetworkGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsTrustController extends Controller
{
    public function __construct(
        private readonly StaffTrustWatchlistService $watchlist,
        private readonly StaffTrustMonitoringService $legacyClusters,
        private readonly UserRiskMonitoringService $riskMonitoring,
        private readonly UserRiskNetworkGraphService $networkGraph,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Operations/Trust/Index', [
            'initialUserId' => $request->integer('user') ?: null,
            'thresholds' => app(\App\Services\TrustRisk\TrustRiskSettingsService::class)->thresholds(),
            'queueCount' => $this->riskMonitoring->queueCount(),
        ]);
    }

    public function riskQueue(Request $request): JsonResponse
    {
        return response()->json($this->riskMonitoring->riskQueue($request));
    }

    public function watchlist(Request $request): JsonResponse
    {
        return response()->json($this->watchlist->watchlist($request->user()));
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json($this->watchlist->feed($request->user()));
    }

    public function clusters(): JsonResponse
    {
        return response()->json($this->legacyClusters->riskClusters());
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

    public function watchlistDetail(Request $request, StaffWatchlistItem $item): JsonResponse
    {
        return response()->json($this->watchlist->watchlistDetail($item, $request->user()));
    }

    public function destroyWatchlist(Request $request, StaffWatchlistItem $item): JsonResponse
    {
        $this->watchlist->removeFromWatchlist($item, $request->user());

        return response()->json(['message' => 'Removed from watchlist.']);
    }

    public function userRisk(Request $request, User $user): JsonResponse
    {
        return response()->json($this->riskMonitoring->userDetail($user));
    }

    public function networkGraph(Request $request, User $user): JsonResponse
    {
        return response()->json($this->networkGraph->graphForUser($user));
    }

    public function networkNotes(Request $request, User $user): JsonResponse
    {
        return response()->json(['notes' => $this->networkGraph->notesForUser($user)]);
    }
}
