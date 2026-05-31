<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestContract;
use App\Services\Contracts\ContractEventLogger;
use App\Services\Contracts\ContractPresentationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminContractController extends Controller
{
    public function show(Request $request, QuestContract $contract, ContractPresentationService $presentation): Response
    {
        abort_unless(in_array($request->user()?->role?->slug, ['admin', 'super_admin'], true), 403);

        return Inertia::render('Contracts/Show', $presentation->showPayload($contract, $request->user(), true));
    }

    public function flagForReview(Request $request, QuestContract $contract, ContractEventLogger $events): RedirectResponse
    {
        $this->authorize('flagForReview', $contract);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $contract->update([
            'flagged_for_review' => true,
            'flagged_for_review_reason' => $data['reason'],
            'flagged_for_review_by' => $request->user()->id,
            'flagged_for_review_at' => now(),
        ]);

        $events->log($contract, 'contract.flagged_for_review', $request->user(), [
            'reason' => $data['reason'],
        ], $request);

        return back()->with('success', __('Contract flagged for financial review.'));
    }
}
