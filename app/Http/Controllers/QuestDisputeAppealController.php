<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disputes\StoreDisputeAppealRequest;
use App\Models\DisputeAppeal;
use App\Models\QuestDispute;
use App\Services\Disputes\DisputeAppealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestDisputeAppealController extends Controller
{
    public function __construct(private readonly DisputeAppealService $appeals) {}

    public function store(StoreDisputeAppealRequest $request, QuestDispute $dispute): RedirectResponse
    {
        $this->authorize('participate', $dispute);

        $this->appeals->fileAppeal($request->user(), $dispute, $request->validated());

        return back()->with('success', __('Your appeal was filed. The other party may respond, then a Super Admin will issue a final binding decision.'));
    }

    public function respond(Request $request, QuestDispute $dispute, DisputeAppeal $appeal): RedirectResponse
    {
        $this->authorize('participate', $dispute);
        abort_unless((int) $appeal->quest_dispute_id === (int) $dispute->id, 404);

        $data = $request->validate([
            'counter_response' => ['nullable', 'string', 'max:3000'],
        ]);

        $this->appeals->respondToAppeal($request->user(), $appeal, $data);

        return back()->with('success', __('Your response was submitted.'));
    }
}
