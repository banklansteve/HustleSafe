<?php

namespace App\Http\Controllers;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Notifications\ProposalAcceptedClientNotification;
use App\Notifications\ProposalAcceptedFreelancerNotification;
use App\Notifications\ProposalDeclinedFreelancerNotification;
use App\Notifications\ProposalEscrowFundedFreelancerNotification;
use App\Notifications\ProposalShortlistedFreelancerNotification;
use App\Notifications\ProposalWithdrawnClientNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestProposalLifecycleController extends Controller
{
    public function shortlist(Request $request, Quest $quest, QuestOffer $offer): RedirectResponse
    {
        $this->authorize('respondAsClient', $offer);
        $request->validate(['confirm' => ['accepted']]);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->assertQuestOpenForDecisions($quest);
        $this->assertOfferDecisible($offer);

        if (! in_array($offer->status, ['submitted', 'shortlisted'], true)) {
            throw ValidationException::withMessages(['proposal' => __('Only open proposals can be shortlisted.')]);
        }

        $offer->update([
            'status' => 'shortlisted',
            'shortlisted_at' => now(),
        ]);

        $offer->freelancer?->notify(new ProposalShortlistedFreelancerNotification($offer));

        return back()->with('success', __('Shortlisted — the freelancer has been nudged in-app.'));
    }

    public function unshortlist(Request $request, Quest $quest, QuestOffer $offer): RedirectResponse
    {
        $this->authorize('respondAsClient', $offer);
        $request->validate(['confirm' => ['accepted']]);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->assertQuestOpenForDecisions($quest);

        if ($offer->status !== 'shortlisted') {
            throw ValidationException::withMessages(['proposal' => __('This proposal is not shortlisted.')]);
        }

        $offer->update([
            'status' => 'submitted',
            'shortlisted_at' => null,
        ]);

        return back()->with('success', __('Moved back to the general proposal list.'));
    }

    public function pin(Request $request, Quest $quest, QuestOffer $offer): RedirectResponse
    {
        $this->authorize('respondAsClient', $offer);
        $request->validate(['confirm' => ['accepted']]);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->assertQuestOpenForDecisions($quest);
        $this->assertOfferDecisible($offer);

        $wasPinned = $offer->client_pinned_at !== null;
        $offer->update(['client_pinned_at' => $wasPinned ? null : now()]);

        return back()->with('success', $wasPinned
            ? __('Unpinned.')
            : __('Pinned for your review queue — easy to spot later.'));
    }

    public function decline(Request $request, Quest $quest, QuestOffer $offer): RedirectResponse
    {
        $this->authorize('respondAsClient', $offer);
        $request->validate([
            'confirm' => ['accepted'],
            'understand_decline' => ['accepted'],
        ]);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->assertQuestOpenForDecisions($quest);
        $this->assertOfferDecisible($offer);

        if (! in_array($offer->status, ['submitted', 'shortlisted'], true)) {
            throw ValidationException::withMessages(['proposal' => __('This proposal is already decided.')]);
        }

        $offer->update([
            'status' => 'declined',
            'declined_at' => now(),
            'shortlisted_at' => null,
            'client_pinned_at' => null,
        ]);

        $offer->freelancer?->notify(new ProposalDeclinedFreelancerNotification($offer));

        return back()->with('success', __('Proposal declined. The freelancer has been notified.'));
    }

    public function accept(Request $request, Quest $quest, QuestOffer $offer): RedirectResponse
    {
        $this->authorize('respondAsClient', $offer);
        $request->validate([
            'confirm' => ['accepted'],
            'accept_escrow_rules' => ['accepted'],
            'accept_fees_and_terms' => ['accepted'],
            'accept_auto_release_ack' => ['accepted'],
        ]);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->assertQuestOpenForDecisions($quest);
        $this->assertOfferDecisible($offer);

        if (! in_array($offer->status, ['submitted', 'shortlisted'], true)) {
            throw ValidationException::withMessages(['proposal' => __('This proposal cannot be accepted in its current state.')]);
        }

        if ($quest->accepted_quest_offer_id !== null) {
            throw ValidationException::withMessages(['proposal' => __('This quest already has an accepted proposal.')]);
        }

        DB::transaction(function () use ($quest, $offer): void {
            QuestOffer::query()
                ->where('quest_id', $quest->id)
                ->where('id', '<>', $offer->id)
                ->whereIn('status', ['submitted', 'shortlisted'])
                ->update([
                    'status' => 'declined',
                    'declined_at' => now(),
                    'shortlisted_at' => null,
                    'client_pinned_at' => null,
                ]);

            $offer->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'shortlisted_at' => null,
            ]);

            $quest->update([
                'freelancer_id' => $offer->freelancer_id,
                'status' => QuestStatus::Assigned,
                'escrow_status' => 'awaiting_funding',
                'accepted_quest_offer_id' => $offer->id,
            ]);
        });

        $quest->client?->notify(new ProposalAcceptedClientNotification($offer));
        $offer->freelancer?->notify(new ProposalAcceptedFreelancerNotification($offer));

        return back()->with('success', __('Proposal accepted. Fund escrow to authorise work — both parties were emailed with next steps.'));
    }

    public function markEscrowFunded(Request $request, Quest $quest, QuestOffer $offer): RedirectResponse
    {
        $this->authorize('respondAsClient', $offer);
        $request->validate([
            'confirm' => ['accepted'],
            'confirm_funds_in_escrow' => ['accepted'],
        ]);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        if ((int) $quest->client_id !== (int) $request->user()?->id) {
            abort(403);
        }

        if ((int) $quest->accepted_quest_offer_id !== (int) $offer->id || $offer->status !== 'accepted') {
            throw ValidationException::withMessages(['proposal' => __('Escrow can only be confirmed for the accepted proposal.')]);
        }

        if ($quest->escrow_status !== 'awaiting_funding') {
            throw ValidationException::withMessages(['proposal' => __('Escrow is not awaiting confirmation right now.')]);
        }

        $quest->update([
            'escrow_status' => 'funded',
            'status' => QuestStatus::InProgress,
        ]);

        $offer->freelancer?->notify(new ProposalEscrowFundedFreelancerNotification($offer));

        return back()->with('success', __('Escrow marked funded — the freelancer is cleared to start work.'));
    }

    public function withdraw(Request $request, Quest $quest, QuestOffer $offer): RedirectResponse
    {
        $this->authorize('withdrawAsFreelancer', $offer);
        $request->validate([
            'confirm' => ['accepted'],
            'understand_withdraw' => ['accepted'],
        ]);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        if (! in_array($offer->status, ['submitted', 'shortlisted'], true)) {
            throw ValidationException::withMessages(['proposal' => __('You can only withdraw proposals that are still pending with the client.')]);
        }

        $offer->update([
            'status' => 'withdrawn',
            'withdrawn_at' => now(),
            'shortlisted_at' => null,
            'client_pinned_at' => null,
        ]);

        $quest->client?->notify(new ProposalWithdrawnClientNotification($offer));

        return redirect()
            ->route('quests.show', $quest)
            ->with('success', __('Proposal withdrawn. You can submit a fresh proposal while the quest stays open.'));
    }

    protected function assertQuestOpenForDecisions(Quest $quest): void
    {
        if ($quest->status !== QuestStatus::Open) {
            throw ValidationException::withMessages(['proposal' => __('This quest is not accepting proposal decisions right now.')]);
        }
    }

    protected function assertOfferDecisible(QuestOffer $offer): void
    {
        if (in_array($offer->status, ['withdrawn', 'declined'], true)) {
            throw ValidationException::withMessages(['proposal' => __('This proposal is closed.')]);
        }
    }
}
