<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disputes\StoreDisputeNegotiationOfferRequest;
use App\Models\DisputeNegotiationOffer;
use App\Models\QuestDispute;
use App\Services\Disputes\DisputeNegotiationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestDisputeNegotiationController extends Controller
{
    public function __construct(private readonly DisputeNegotiationService $negotiation) {}

    public function propose(StoreDisputeNegotiationOfferRequest $request, QuestDispute $dispute): RedirectResponse
    {
        $this->authorize('participate', $dispute);

        $this->negotiation->propose($request->user(), $dispute, $request->validated());

        return back()->with('success', __('Your proposal was sent to the other party.'));
    }

    public function accept(Request $request, QuestDispute $dispute, DisputeNegotiationOffer $offer): RedirectResponse
    {
        $this->authorize('participate', $dispute);
        abort_unless((int) $offer->quest_dispute_id === (int) $dispute->id, 404);

        $this->negotiation->accept($request->user(), $offer);

        return back()->with('success', __('You accepted the proposal. Customer Support will review before it is enforced.'));
    }

    public function reject(Request $request, QuestDispute $dispute, DisputeNegotiationOffer $offer): RedirectResponse
    {
        $this->authorize('participate', $dispute);
        abort_unless((int) $offer->quest_dispute_id === (int) $dispute->id, 404);

        $this->negotiation->reject($request->user(), $offer);

        return back()->with('success', __('You rejected the final offer. A staff mediator will review the case.'));
    }

    public function acknowledgeBinding(Request $request, QuestDispute $dispute): RedirectResponse
    {
        $this->authorize('participate', $dispute);

        $this->negotiation->acknowledgeBindingMediation($request->user(), $dispute);

        return back()->with('success', __('You acknowledged that mediation decisions on HustleSafe are binding.'));
    }
}
