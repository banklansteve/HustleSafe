<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contracts\RespondContractDeliveryExtensionRequest;
use App\Http\Requests\Contracts\StoreContractDeliveryExtensionRequest;
use App\Models\QuestContract;
use App\Models\QuestContractDeliveryExtension;
use App\Models\QuestConversationMessage;
use App\Models\QuestConversationThread;
use App\Services\Contracts\ContractDeliveryExtensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractDeliveryExtensionController extends Controller
{
    public function store(
        StoreContractDeliveryExtensionRequest $request,
        QuestContract $contract,
        ContractDeliveryExtensionService $service,
    ): RedirectResponse {
        $this->authorize('requestDeliveryExtension', $contract);

        $service->queueRequest($contract, $request->user(), $request->validated(), $request);

        return back()->with('success', __('Extension request submitted. The client has been notified.'));
    }

    public function respond(
        RespondContractDeliveryExtensionRequest $request,
        QuestContract $contract,
        QuestContractDeliveryExtension $extension,
        ContractDeliveryExtensionService $service,
    ): RedirectResponse {
        $this->authorize('respondDeliveryExtension', $contract);

        if ((int) $extension->quest_contract_id !== (int) $contract->id) {
            abort(404);
        }

        $action = $request->validated()['action'];
        $user = $request->user();

        match ($action) {
            'accept' => $service->clientAccept($contract, $extension, $user, $request),
            'decline' => $service->clientDecline($contract, $extension, $user, (string) $request->validated()['decline_reason'], $request),
            'counter' => $service->clientCounterPropose($contract, $extension, $user, (string) $request->validated()['counter_proposed_date'], $request),
        };

        return back()->with('success', __('Your response has been recorded.'));
    }

    public function respondCounter(
        Request $request,
        QuestContract $contract,
        QuestContractDeliveryExtension $extension,
        ContractDeliveryExtensionService $service,
    ): RedirectResponse {
        $this->authorize('requestDeliveryExtension', $contract);

        if ((int) $extension->quest_contract_id !== (int) $contract->id) {
            abort(404);
        }

        $request->validate(['action' => ['required', 'in:accept,decline']]);

        if ($request->input('action') === 'accept') {
            $service->freelancerAcceptCounter($contract, $extension, $request->user(), $request);
        } else {
            $service->freelancerDeclineCounter($contract, $extension, $request->user(), $request);
        }

        return back()->with('success', __('Your response has been recorded.'));
    }

    public function conversationMessages(Request $request, QuestContract $contract): JsonResponse
    {
        $this->authorize('view', $contract);

        $thread = QuestConversationThread::query()
            ->where('quest_id', $contract->quest_id)
            ->where('freelancer_id', $contract->freelancer_id)
            ->first();

        if ($thread === null) {
            return response()->json(['messages' => []]);
        }

        $messages = QuestConversationMessage::query()
            ->where('quest_conversation_thread_id', $thread->id)
            ->with('user:id,name')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (QuestConversationMessage $m) => [
                'id' => $m->id,
                'body' => $m->is_redacted ? ($m->redaction_label ?? '[redacted]') : $m->body,
                'author' => $m->user?->name ?? __('Unknown'),
                'author_id' => $m->user_id,
                'is_client' => (int) $m->user_id === (int) $contract->client_id,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return response()->json(['messages' => $messages]);
    }
}
