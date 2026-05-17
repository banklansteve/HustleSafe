<?php

namespace App\Http\Controllers;

use App\Enums\DisputeMessageKind;
use App\Http\Requests\Disputes\StoreDisputeMessageRequest;
use App\Jobs\ScanContentForModerationJob;
use App\Models\DisputeMessage;
use App\Models\QuestDispute;
use App\Services\Disputes\QuestDisputeWorkflowService;
use Illuminate\Http\RedirectResponse;

class QuestDisputeMessageController extends Controller
{
    public function store(StoreDisputeMessageRequest $request, QuestDispute $dispute, QuestDisputeWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('participate', $dispute);

        $data = $request->validated();
        $kind = DisputeMessageKind::from($data['kind']);

        $message = $workflow->addPartyMessage(
            $request->user(),
            $dispute,
            $kind,
            $data['body'] ?? null,
            $data['structured_key'] ?? null,
            $data['structured_payload'] ?? null,
        );
        ScanContentForModerationJob::dispatch(DisputeMessage::class, (int) $message->id)->afterResponse();

        return back()->with('success', __('Update posted to the dispute file.'));
    }
}
