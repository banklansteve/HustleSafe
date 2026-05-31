<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contracts\RespondContractAmendmentRequest;
use App\Http\Requests\Contracts\StoreContractAmendmentRequest;
use App\Models\QuestContract;
use App\Models\QuestContractAmendment;
use App\Services\Contracts\ContractAmendmentService;
use Illuminate\Http\RedirectResponse;

class ContractAmendmentController extends Controller
{
    public function store(
        StoreContractAmendmentRequest $request,
        QuestContract $contract,
        ContractAmendmentService $amendments,
    ): RedirectResponse {
        $this->authorize('requestAmendment', $contract);

        $amendments->request($contract, $request->user(), $request->validated(), $request);

        return back()->with('success', __('Amendment request sent to the other party.'));
    }

    public function respond(
        RespondContractAmendmentRequest $request,
        QuestContract $contract,
        QuestContractAmendment $amendment,
        ContractAmendmentService $amendments,
    ): RedirectResponse {
        $this->authorize('view', $contract);

        if ((int) $amendment->quest_contract_id !== (int) $contract->id) {
            abort(404);
        }

        if ($request->validated()['action'] === 'accept') {
            $amendments->accept($contract, $amendment, $request->user(), $request);

            return back()->with('success', __('Amendment accepted. Contract terms updated.'));
        }

        $amendments->decline($contract, $amendment, $request->user(), (string) $request->validated()['response_note'], $request);

        return back()->with('success', __('Amendment declined. Original terms remain in force.'));
    }
}
