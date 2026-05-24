<?php

namespace App\Http\Controllers;

use App\Enums\AdminProposalStatus;
use App\Enums\QuestStatus;
use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Services\Payments\PaystackClient;
use App\Notifications\ProposalAcceptedClientNotification;
use App\Notifications\ProposalAcceptedFreelancerNotification;
use App\Notifications\ProposalDeclinedFreelancerNotification;
use App\Notifications\ProposalEscrowFundedFreelancerNotification;
use App\Notifications\ProposalShortlistedFreelancerNotification;
use App\Notifications\ProposalWithdrawnClientNotification;
use App\Services\Admin\AdminActivityFeedService;
use App\Services\Verification\VerificationEngineService;
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

    public function accept(Request $request, Quest $quest, QuestOffer $offer, VerificationEngineService $verificationEngine): RedirectResponse
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

        if ($verificationEngine->arbitrationRequired($quest, $offer)) {
            $verificationEngine->recordArbitrationAgreement($quest, $offer, $request->user(), 'client');
        }

        $quest->client?->notify(new ProposalAcceptedClientNotification($offer));
        $offer->freelancer?->notify(new ProposalAcceptedFreelancerNotification($offer));
        $quest->loadMissing(['client', 'questCategory', 'stateModel']);
        $offer->loadMissing('freelancer');
        app(AdminActivityFeedService::class)->record(
            'financial',
            'contract.started',
            'Contract started',
            "{$quest->client?->name} accepted {$offer->freelancer?->name} for {$quest->title}",
            app(AdminActivityFeedService::class)->entities([
                ['type' => 'user', 'id' => $quest->client_id, 'label' => $quest->client?->name],
                ['type' => 'user', 'id' => $offer->freelancer_id, 'label' => $offer->freelancer?->name],
                ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
            ]),
            ['category' => $quest->questCategory?->name, 'state' => $quest->stateModel?->name],
            (int) ($offer->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0),
            $request->user(),
            QuestOffer::class,
            $offer->id,
            $quest->state_id,
            $quest->local_government_id,
            $quest->quest_category_id,
            'info',
        );

        return back()->with('success', __('Proposal accepted. Fund escrow to authorise work — both parties were emailed with next steps.'));
    }

    public function markEscrowFunded(Request $request, Quest $quest, QuestOffer $offer, VerificationEngineService $verificationEngine): RedirectResponse
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

        if ($verificationEngine->arbitrationRequired($quest, $offer) && ! $verificationEngine->hasBothArbitrationAgreements($quest, $offer)) {
            throw ValidationException::withMessages(['arbitration' => __('Both parties must accept platform-mediated arbitration before this high-value Quest can move to In Progress.')]);
        }

        $paymentEscrow = PaymentEscrow::query()->where('quest_id', $quest->id)->first();
        if (app(PaystackClient::class)->enabled()) {
            if ($paymentEscrow?->status !== 'funded') {
                throw ValidationException::withMessages([
                    'escrow' => [__('Complete payment via Paystack before confirming escrow.')],
                ]);
            }

            return back()->with('success', __('Escrow is already funded via Paystack.'));
        }

        $quest->update([
            'escrow_status' => 'funded',
            'status' => QuestStatus::InProgress,
            'escrow_funded_at' => now(),
        ]);

        $offer->freelancer?->notify(new ProposalEscrowFundedFreelancerNotification($offer));
        $quest->loadMissing(['client', 'questCategory', 'stateModel']);
        $offer->loadMissing('freelancer');
        app(AdminActivityFeedService::class)->record(
            'financial',
            'escrow.funded',
            'Escrow funded',
            "{$quest->client?->name} funded escrow for {$quest->title}",
            app(AdminActivityFeedService::class)->entities([
                ['type' => 'user', 'id' => $quest->client_id, 'label' => $quest->client?->name],
                ['type' => 'user', 'id' => $offer->freelancer_id, 'label' => $offer->freelancer?->name],
                ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
            ]),
            ['category' => $quest->questCategory?->name, 'state' => $quest->stateModel?->name],
            (int) ($offer->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0),
            $request->user(),
            Quest::class,
            $quest->id,
            $quest->state_id,
            $quest->local_government_id,
            $quest->quest_category_id,
            'success',
        );

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
        $adminStatus = $offer->admin_status?->value ?? (string) $offer->admin_status;
        if ($adminStatus === AdminProposalStatus::Suspended->value) {
            throw ValidationException::withMessages(['proposal' => __('This proposal is suspended by HustleSafe moderation.')]);
        }

        if ($adminStatus === AdminProposalStatus::Restricted->value) {
            throw ValidationException::withMessages(['proposal' => __('This proposal is visible but cannot be accepted until moderation releases it.')]);
        }

        if (in_array($offer->status, ['withdrawn', 'declined'], true)) {
            throw ValidationException::withMessages(['proposal' => __('This proposal is closed.')]);
        }
    }
}
