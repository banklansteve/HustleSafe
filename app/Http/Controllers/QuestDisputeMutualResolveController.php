<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disputes\StoreDisputeMutualResolveRequest;
use App\Models\QuestDispute;
use App\Services\Disputes\QuestDisputeWorkflowService;
use Illuminate\Http\RedirectResponse;

class QuestDisputeMutualResolveController extends Controller
{
    public function store(StoreDisputeMutualResolveRequest $request, QuestDispute $dispute, QuestDisputeWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('participate', $dispute);

        $workflow->recordMutualResolveAck($request->user(), $dispute);

        return back()->with('success', __('Your agreement to resolve was recorded.'));
    }
}
