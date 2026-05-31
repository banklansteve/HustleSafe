<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\StoreProposalClarificationAnswerRequest;
use App\Http\Requests\Quests\StoreProposalClarificationQuestionRequest;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Services\Proposals\ProposalClarificationService;
use App\Services\UserNotificationInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProposalClarificationController extends Controller
{
    public function show(Quest $quest, QuestOffer $offer, ProposalClarificationService $clarifications, Request $request): Response|JsonResponse
    {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $isClient = (int) $quest->client_id === (int) $user->id;
        $isFreelancer = (int) $offer->freelancer_id === (int) $user->id;
        if (! $isClient && ! $isFreelancer) {
            abort(403);
        }

        app(UserNotificationInboxService::class)->markProposalClarificationForOffer(
            $user,
            (int) $quest->id,
            (int) $offer->id,
        );

        $payload = $clarifications->payloadFor($offer, $user);

        if ($request->wantsJson()) {
            $afterId = (int) $request->query('after_id', 0);
            if ($afterId > 0) {
                $payload['thread']['messages'] = collect($payload['thread']['messages'])
                    ->filter(fn (array $message) => (int) ($message['id'] ?? 0) > $afterId)
                    ->values()
                    ->all();
            }

            return response()->json($payload);
        }

        return Inertia::render('Quests/Proposals/Clarify', $payload);
    }

    public function ask(
        StoreProposalClarificationQuestionRequest $request,
        Quest $quest,
        QuestOffer $offer,
        ProposalClarificationService $clarifications,
    ): RedirectResponse|JsonResponse {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $message = $clarifications->askQuestion(
            $offer,
            $request->user(),
            (string) $request->validated('body'),
            $request->validated('prompt_key'),
            $request->validated('prompt_category'),
        );

        $thread = $clarifications->threadForOffer($offer);
        $offer->loadMissing('quest');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $clarifications->formatMessage($message),
                'thread' => $clarifications->threadMetaFor($thread, $offer, $offer->quest),
                'flash' => __('Question sent — the freelancer can reply in this thread only.'),
            ]);
        }

        return back()->with('success', __('Question sent — the freelancer can reply in this thread only.'));
    }

    public function answer(
        StoreProposalClarificationAnswerRequest $request,
        Quest $quest,
        QuestOffer $offer,
        ProposalClarificationService $clarifications,
    ): RedirectResponse|JsonResponse {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $message = $clarifications->postAnswer(
            $offer,
            $request->user(),
            (string) $request->validated('body'),
            (int) $request->validated('reply_to_message_id'),
        );

        $thread = $clarifications->threadForOffer($offer);
        $offer->loadMissing('quest');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $clarifications->formatMessage($message),
                'thread' => $clarifications->threadMetaFor($thread, $offer, $offer->quest),
                'flash' => __('Answer posted — the client has been notified.'),
            ]);
        }

        return back()->with('success', __('Answer posted — the client has been notified.'));
    }
}
