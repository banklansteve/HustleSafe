<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PremiumPatrol\PremiumPatrolActionRequest;
use App\Http\Requests\Admin\PremiumPatrol\PremiumPatrolGrantBoostRequest;
use App\Http\Requests\Admin\PremiumPatrol\PremiumPatrolGrantPremiumRequest;
use App\Models\PremiumPatrolFlag;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\User;
use App\Services\Admin\PremiumPatrol\PremiumPatrolActionService;
use App\Services\Admin\PremiumPatrol\PremiumPatrolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPremiumPatrolController extends Controller
{
    public function __construct(
        private readonly PremiumPatrolService $patrol,
        private readonly PremiumPatrolActionService $actions,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/PremiumPatrol/Index', $this->patrol->indexPayload($request));
    }

    public function metrics(Request $request): JsonResponse
    {
        return response()->json($this->patrol->metricsApi($request));
    }

    public function premiumUserDetail(User $user): JsonResponse
    {
        return response()->json($this->patrol->premiumUserDetail($user));
    }

    public function boostDetail(QuestBoost $questBoost): JsonResponse
    {
        return response()->json($this->patrol->boostDetail($questBoost));
    }

    public function dismissFlag(Request $request, PremiumPatrolFlag $flag): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->actions->dismissFlag($flag, $request->user(), (string) $request->input('reason'));

        return back()->with('success', __('Anomaly dismissed.'));
    }

    public function suspendPremium(PremiumPatrolActionRequest $request, User $user): RedirectResponse
    {
        $this->actions->suspendPremium($user, $request->user(), $request->validated());

        return back()->with('success', __('Premium subscription suspended.'));
    }

    public function refundPremium(PremiumPatrolActionRequest $request, User $user): RedirectResponse
    {
        $this->actions->refundPremium($user, $request->user(), $request->validated());

        return back()->with('success', __('Premium charge refunded.'));
    }

    public function grantPremium(PremiumPatrolGrantPremiumRequest $request, User $user): RedirectResponse
    {
        $this->actions->grantPremium($user, $request->user(), $request->validated());

        return back()->with('success', __('User upgraded to premium.'));
    }

    public function flagManualReview(PremiumPatrolActionRequest $request, User $user): RedirectResponse
    {
        $this->actions->flagManualReview($user, $request->user(), $request->validated());

        return back()->with('success', __('User flagged for manual review.'));
    }

    public function addPremiumWatchlist(PremiumPatrolActionRequest $request, User $user): RedirectResponse
    {
        $this->actions->addToWatchlist($user, $request->user(), 'premium', $request->validated());

        return back()->with('success', __('User added to premium watchlist.'));
    }

    public function investigatePremium(PremiumPatrolActionRequest $request, User $user): RedirectResponse
    {
        $this->actions->openInvestigation('premium_user', $user->id, $request->user(), $request->validated());

        return back()->with('success', __('Investigation case opened.'));
    }

    public function demoteBoost(PremiumPatrolActionRequest $request, QuestBoost $questBoost): RedirectResponse
    {
        $this->actions->demoteBoost($questBoost, $request->user(), $request->validated());

        return back()->with('success', __('Boost demoted.'));
    }

    public function refundBoost(PremiumPatrolActionRequest $request, QuestBoost $questBoost): RedirectResponse
    {
        $this->actions->refundBoost($questBoost, $request->user(), $request->validated());

        return back()->with('success', __('Boost fee refunded.'));
    }

    public function suspendQuest(PremiumPatrolActionRequest $request, Quest $quest): RedirectResponse
    {
        $this->actions->suspendQuest($quest, $request->user(), $request->validated());

        return back()->with('success', __('Quest suspended.'));
    }

    public function investigateBoost(PremiumPatrolActionRequest $request, QuestBoost $questBoost): RedirectResponse
    {
        $this->actions->openInvestigation('boosted_quest', $questBoost->id, $request->user(), $request->validated());

        return back()->with('success', __('Investigation case opened.'));
    }

    public function requestVerification(PremiumPatrolActionRequest $request, QuestBoost $questBoost): RedirectResponse
    {
        $this->actions->requestClientVerification($questBoost, $request->user(), $request->validated());

        return back()->with('success', __('Verification request sent to client.'));
    }

    public function grantBoost(PremiumPatrolGrantBoostRequest $request): RedirectResponse
    {
        $boost = $this->actions->grantBoost($request->validated(), $request->user());
        $boost->client?->notify(new \App\Notifications\QuestBoostAdminGrantedNotification(
            $boost,
            (string) ($request->input('reason_notes') ?? $request->input('grant_reason') ?? ''),
        ));

        return back()->with('success', __('Quest boost granted.'));
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q): void {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%");
            }))
            ->limit(15)
            ->get(['id', 'name', 'email', 'username', 'verification_tier']);

        return response()->json(['data' => $users]);
    }
}
