<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\StoreProposalClarificationAnswerRequest;
use App\Http\Requests\Quests\StoreProposalClarificationQuestionRequest;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Services\Proposals\ProposalClarificationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProposalClarificationController extends Controller
{
    public function show(Quest $quest, QuestOffer $offer, ProposalClarificationService $clarifications): Response
    {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $user = request()->user();
        if ($user === null) {
            abort(403);
        }

        $isClient = (int) $quest->client_id === (int) $user->id;
        $isFreelancer = (int) $offer->freelancer_id === (int) $user->id;
        if (! $isClient && ! $isFreelancer) {
            abort(403);
        }

        return Inertia::render('Quests/Proposals/Clarify', $clarifications->payloadFor($offer, $user));
    }

    public function ask(
        StoreProposalClarificationQuestionRequest $request,
        Quest $quest,
        QuestOffer $offer,
        ProposalClarificationService $clarifications,
    ): RedirectResponse {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $clarifications->askQuestion(
            $offer,
            $request->user(),
            (string) $request->validated('body'),
            $request->validated('prompt_key'),
            $request->validated('prompt_category'),
        );

        return back()->with('success', __('Question sent — the freelancer can reply in this thread only.'));
    }

    public function answer(
        StoreProposalClarificationAnswerRequest $request,
        Quest $quest,
        QuestOffer $offer,
        ProposalClarificationService $clarifications,
    ): RedirectResponse {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $clarifications->postAnswer(
            $offer,
            $request->user(),
            (string) $request->validated('body'),
            (int) $request->validated('reply_to_message_id'),
        );

        return back()->with('success', __('Answer posted — the client has been notified.'));
    }
}
