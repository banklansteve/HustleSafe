<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disputes\StoreDisputeSettlementOfferRequest;
use App\Models\DisputeSettlementOffer;
use App\Models\QuestDispute;
use App\Services\Disputes\QuestDisputeWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestDisputeSettlementController extends Controller
{
    public function store(StoreDisputeSettlementOfferRequest $request, QuestDispute $dispute, QuestDisputeWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('participate', $dispute);

        $workflow->submitSettlementOffer(
            $request->user(),
            $dispute,
            (int) $request->validated('client_share_percent'),
            $request->validated('note'),
        );

        return back()->with('success', __('Settlement offer recorded.'));
    }

    public function accept(Request $request, QuestDispute $dispute, DisputeSettlementOffer $settlement_offer, QuestDisputeWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('participate', $dispute);

        if ((int) $settlement_offer->quest_dispute_id !== (int) $dispute->id) {
            abort(404);
        }

        $workflow->acceptSettlementOffer($request->user(), $settlement_offer);

        return back()->with('success', __('Settlement accepted — case marked resolved pending payout rails.'));
    }

    public function decline(Request $request, QuestDispute $dispute, DisputeSettlementOffer $settlement_offer, QuestDisputeWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('participate', $dispute);

        if ((int) $settlement_offer->quest_dispute_id !== (int) $dispute->id) {
            abort(404);
        }

        $workflow->declineSettlementOffer($request->user(), $settlement_offer);

        return back()->with('success', __('Offer declined — continue negotiation or wait for the next timer milestone.'));
    }
}
