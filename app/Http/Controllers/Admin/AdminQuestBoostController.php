<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantQuestBoostRequest;
use App\Http\Requests\Admin\UpdateQuestBoostDatesRequest;
use App\Models\QuestBoost;
use App\Services\Admin\QuestBoostReportService;
use App\Services\Admin\QuestBoostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminQuestBoostController extends Controller
{
    public function __construct(
        private readonly QuestBoostService $boosts,
        private readonly QuestBoostReportService $reports,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/QuestBoosts/Index', $this->boosts->indexPayload($request));
    }

    public function show(QuestBoost $questBoost): Response
    {
        return Inertia::render('Admin/QuestBoosts/Show', $this->boosts->detailPayload($questBoost));
    }

    public function store(GrantQuestBoostRequest $request): RedirectResponse
    {
        $boost = $this->boosts->grant($request->validated(), $request->user());

        return redirect()
            ->route('admin.quest-boosts.show', $boost)
            ->with('success', __('Quest boost granted.'));
    }

    public function updateDates(UpdateQuestBoostDatesRequest $request, QuestBoost $questBoost): RedirectResponse
    {
        $this->boosts->updateDates($questBoost, $request->validated(), $request->user());

        return back()->with('success', __('Boost dates updated.'));
    }

    public function endEarly(Request $request, QuestBoost $questBoost): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->boosts->endEarly($questBoost, $request->user(), (string) $request->input('reason'));

        return back()->with('success', __('Boost ended early.'));
    }

    public function cancel(Request $request, QuestBoost $questBoost): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->boosts->cancel($questBoost, $request->user(), (string) $request->input('reason'));

        return back()->with('success', __('Boost cancelled.'));
    }

    public function searchQuests(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'data' => $this->boosts->searchQuests((string) $request->query('q', '')),
        ]);
    }

    public function report(Request $request): Response
    {
        return Inertia::render('Admin/QuestBoosts/Report', $this->reports->report($request));
    }

    public function exportReport(Request $request): StreamedResponse
    {
        return $this->reports->exportCsv($request);
    }
}
