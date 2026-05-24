<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffWatchlistItem;
use App\Models\User;
use App\Services\Operations\StaffTrustMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsTrustController extends Controller
{
    public function __construct(private readonly StaffTrustMonitoringService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/Trust/Index');
    }

    public function watchlist(Request $request): JsonResponse
    {
        return response()->json($this->service->watchlist($request->user()));
    }

    public function clusters(): JsonResponse
    {
        return response()->json($this->service->riskClusters());
    }

    public function storeWatchlist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'watchable_type' => ['required', 'string'],
            'watchable_id' => ['required', 'integer'],
            'label' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
        ]);

        $data['watchable_type'] = match ($data['watchable_type']) {
            'User' => User::class,
            'Quest' => \App\Models\Quest::class,
            'QuestOffer' => \App\Models\QuestOffer::class,
            default => $data['watchable_type'],
        };

        $item = $this->service->addToWatchlist($request->user(), $data);

        return response()->json(['message' => 'Added to watchlist.', 'item' => $item->id]);
    }

    public function watchlistDetail(Request $request, StaffWatchlistItem $item): JsonResponse
    {
        return response()->json($this->service->watchlistDetail($item, $request->user()));
    }

    public function destroyWatchlist(Request $request, StaffWatchlistItem $item): JsonResponse
    {
        $this->service->removeFromWatchlist($item, $request->user());

        return response()->json(['message' => 'Removed from watchlist.']);
    }
}
