<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\ContinueQuestContractRequest;
use App\Http\Requests\Quests\ExtendQuestContractRequest;
use App\Http\Requests\Quests\RepublishQuestContractRequest;
use App\Models\Quest;
use App\Services\Quest\QuestContractRenewalService;
use Illuminate\Http\RedirectResponse;

class QuestContractRenewalController extends Controller
{
    public function extend(
        ExtendQuestContractRequest $request,
        Quest $quest,
        QuestContractRenewalService $renewals,
    ): RedirectResponse {
        $this->authorize('manageContractRenewal', $quest);

        $renewals->extend(
            $quest,
            $request->user(),
            (int) $request->validated('additional_months'),
        );

        return back()->with('success', __('Contract extended. Fund the additional escrow so the extra payment periods can run.'));
    }

    public function continueWithFreelancer(
        ContinueQuestContractRequest $request,
        Quest $quest,
        QuestContractRenewalService $renewals,
    ): RedirectResponse {
        $this->authorize('manageContractRenewal', $quest);

        $renewals->continueWithFreelancer(
            $quest,
            $request->user(),
            (int) $request->validated('contract_duration_months'),
        );

        return back()->with('success', __('New contract cycle started with your worker. Fund escrow to begin the next round of payments.'));
    }

    public function republish(
        RepublishQuestContractRequest $request,
        Quest $quest,
        QuestContractRenewalService $renewals,
    ): RedirectResponse {
        $this->authorize('manageContractRenewal', $quest);

        $fresh = $renewals->republishForProposals($quest, $request->user());

        return redirect()
            ->route('quests.show', $fresh)
            ->with('success', __('Quest republished with a fresh proposal window.'));
    }
}
