<?php

namespace App\Http\Controllers;

use App\Enums\AdminProposalStatus;
use App\Enums\QuestStatus;
use App\Events\QuestProposalListUpdated;
use App\Http\Requests\Quests\CancelProposalAwardRequest;
use App\Http\Requests\Quests\ConfirmProposalAwardRequest;
use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\ProposalAwardPendingFreelancerNotification;
use App\Notifications\ProposalDeclinedFreelancerNotification;
use App\Notifications\ProposalEscrowFundedFreelancerNotification;
use App\Notifications\ProposalWithdrawnClientNotification;
use App\Services\Admin\AdminActivityFeedService;
use App\Services\Payments\PaystackClient;
use App\Jobs\FinalizeProposalAwardJob;
use App\Services\Proposals\ProposalAwardCancellationService;
use App\Services\Proposals\ProposalClarificationPromptService;
use App\Services\Proposals\ProposalClarificationService;
use App\Services\Proposals\ProposalShortlistService;
use App\Services\Proposals\ProposalTrustBehaviourService;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestProposalLifecycleController extends Controller
{
    public function toggleShortlist(Request $request, Quest $quest, QuestOffer $offer, ProposalShortlistService $shortlists): JsonResponse|RedirectResponse
    {
        $this->authorize('respondAsClient', $offer);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->assertQuestOpenForDecisions($quest);
        $this->assertOfferDecisible($offer);

        $result = $shortlists->toggle($quest, $offer);
        $fresh = $offer->fresh();

        $this->recordProposalActivity(
            $request->user(),
            $result['shortlisted'] ? 'proposal.shortlisted' : 'proposal.unshortlisted',
            $result['shortlisted'] ? 'Shortlisted proposal' : 'Removed proposal from shortlist',
            $quest,
            $fresh,
            $request,
        );

        try {
            broadcast(new QuestProposalListUpdated((int) $quest->id));
        } catch (\Throwable $exception) {
            report($exception);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'shortlisted' => $result['shortlisted'],
                'status' => $fresh->status,
                'shortlist_count' => $result['count'],
                'shortlist_max' => $result['max'],
                'flash' => $result['shortlisted']
                    ? __('Shortlisted — the freelancer has been notified in-app.')
                    : __('Removed from your shortlist.'),
            ]);
        }

        return back()->with('success', $result['shortlisted']
            ? __('Shortlisted — the freelancer has been notified in-app.')
            : __('Removed from your shortlist.'));
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

        app(\App\Services\Quest\QuestJourneySurveyService::class)->onProposalRejected($quest, $offer, 'declined');

        $this->recordProposalActivity($request->user(), 'proposal.declined', 'Declined proposal', $quest, $offer, $request);

        return back()->with('success', __('Proposal declined. The freelancer has been notified.'));
    }

    public function accept(
        Request $request,
        Quest $quest,
        QuestOffer $offer,
        ProposalClarificationPromptService $promptService,
        ProposalClarificationService $clarifications,
        VerificationEngineService $verificationEngine,
    ): RedirectResponse {
        $this->authorize('respondAsClient', $offer);
        $request->validate([
            'confirm_award_terms' => ['accepted'],
            'accept_escrow_rules' => ['accepted'],
            'accept_fees_and_terms' => ['accepted'],
        ]);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->assertQuestOpenForDecisions($quest);
        $this->assertOfferDecisible($offer);

        if (! in_array($offer->status, ['submitted', 'shortlisted'], true)) {
            throw ValidationException::withMessages(['proposal' => __('This proposal cannot be awarded in its current state.')]);
        }

        $recurring = app(\App\Services\Quest\QuestRecurringEngagementService::class);
        if ($recurring->isRecurring($quest) && ! $offer->accepts_installment_terms) {
            throw ValidationException::withMessages([
                'proposal' => __('This worker has not accepted the installment payment schedule. Choose another proposal or ask them to resubmit.'),
            ]);
        }

        if ($quest->accepted_quest_offer_id !== null || $quest->pending_award_offer_id !== null) {
            throw ValidationException::withMessages(['proposal' => __('This quest already has an award in progress or accepted.')]);
        }

        $terms = $promptService->awardTermsSnapshot($quest, $offer);
        $terms['prior_status'] = $offer->status;
        $terms['client_confirmation'] = [
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'confirmed_at' => now()->toIso8601String(),
            'action' => __('I agree to the terms of this contract'),
        ];
        $terms['revisions_included'] = (int) ($offer->corrections_included ? ($offer->corrections_rounds ?: 1) : 0);
        $terms['revision_definition'] = $promptService->deriveRevisionDefinition($offer);
        $terms['deliverables'] = $promptService->deriveDeliverables($quest, $offer);

        $offer->update([
            'status' => 'pending_award',
            'shortlisted_at' => null,
            'award_client_confirmed_at' => now(),
            'award_terms_snapshot' => $terms,
        ]);

        $quest->update(['pending_award_offer_id' => $offer->id]);
        $clarifications->closeForOffer($offer);

        if ($verificationEngine->arbitrationRequired($quest, $offer)) {
            $verificationEngine->recordArbitrationAgreement($quest, $offer, $request->user(), 'client');
        }

        $offer->freelancer?->notify(new ProposalAwardPendingFreelancerNotification($offer, $terms));

        $this->recordProposalActivity($request->user(), 'proposal.award_initiated', 'Initiated proposal award', $quest, $offer, $request);

        return back()->with('success', __('You chose this worker. They must confirm before you can pay into escrow.'));
    }

    public function confirmAward(
        ConfirmProposalAwardRequest $request,
        Quest $quest,
        QuestOffer $offer,
        VerificationEngineService $verificationEngine,
        ProposalClarificationService $clarifications,
    ): RedirectResponse {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        if ((int) $offer->freelancer_id !== (int) $request->user()?->id) {
            abort(403);
        }

        if ($offer->status !== 'pending_award' || $offer->award_client_confirmed_at === null) {
            throw ValidationException::withMessages(['proposal' => __('This award is not awaiting your confirmation.')]);
        }

        if ((int) $quest->pending_award_offer_id !== (int) $offer->id) {
            throw ValidationException::withMessages(['proposal' => __('This award is no longer active.')]);
        }

        $otherOfferIds = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->where('id', '<>', $offer->id)
            ->whereIn('status', ['submitted', 'shortlisted', 'pending_award'])
            ->pluck('id');

        $freelancerConfirmation = [
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'confirmed_at' => now()->toIso8601String(),
            'action' => __('I agree to the terms of this contract'),
        ];

        DB::transaction(function () use ($quest, $offer, $freelancerConfirmation, $otherOfferIds): void {
            QuestOffer::query()
                ->where('quest_id', $quest->id)
                ->where('id', '<>', $offer->id)
                ->whereIn('status', ['submitted', 'shortlisted', 'pending_award'])
                ->update([
                    'status' => 'declined',
                    'declined_at' => now(),
                    'shortlisted_at' => null,
                    'client_pinned_at' => null,
                ]);

            $snapshot = $offer->award_terms_snapshot ?? [];
            if (! is_array($snapshot)) {
                $snapshot = [];
            }
            $snapshot['freelancer_confirmation'] = $freelancerConfirmation;
            $snapshot['declined_offer_ids'] = $otherOfferIds->map(fn ($id) => (int) $id)->values()->all();

            $offer->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'award_freelancer_confirmed_at' => now(),
                'award_terms_snapshot' => $snapshot,
            ]);

            $quest->update([
                'freelancer_id' => $offer->freelancer_id,
                'status' => QuestStatus::Assigned,
                'escrow_status' => 'awaiting_funding',
                'accepted_quest_offer_id' => $offer->id,
                'pending_award_offer_id' => null,
            ]);
        });

        $clarifications->closeForOffer($offer);

        if ($verificationEngine->arbitrationRequired($quest, $offer)) {
            $verificationEngine->recordArbitrationAgreement($quest, $offer, $request->user(), 'freelancer');
        }

        FinalizeProposalAwardJob::dispatch(
            questId: (int) $quest->id,
            offerId: (int) $offer->id,
            declinedOfferIds: $otherOfferIds->map(fn ($id) => (int) $id)->values()->all(),
        )->onConnection('database');

        $offer->refresh()->loadMissing(['quest', 'freelancer', 'quest.client']);
        $quest->refresh()->loadMissing('client');
        $quest->client?->notify(new \App\Notifications\ProposalAcceptedClientNotification($offer));

        $this->recordProposalActivity($request->user(), 'proposal.award_confirmed', 'Confirmed proposal award', $quest, $offer, $request);

        return back()
            ->with('success', __('Award confirmed — the client can now fund escrow to start work.'));
    }

    public function cancelAward(
        CancelProposalAwardRequest $request,
        Quest $quest,
        QuestOffer $offer,
        ProposalAwardCancellationService $cancellations,
    ): RedirectResponse {
        $this->authorize('respondAsClient', $offer);

        $cancellations->cancel(
            $quest,
            $offer,
            $request->user(),
            $request->validated('reason'),
        );

        $this->recordProposalActivity($request->user(), 'proposal.award_cancelled', 'Cancelled proposal award', $quest, $offer, $request);

        return redirect()
            ->route('quests.client.proposals.index', $quest)
            ->with('success', __('Award cancelled. The freelancer has been notified and the quest is open again.'));
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

        if (! $offer->isAwardMutuallyConfirmed()) {
            throw ValidationException::withMessages(['proposal' => __('Both parties must confirm the award terms before funding escrow.')]);
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
        app(\App\Services\Contracts\ContractLifecycleService::class)->activateFromEscrowFunding(
            $quest->fresh(),
            $paymentEscrow ?? PaymentEscrow::query()->where('quest_id', $quest->id)->firstOrFail(),
            $request,
        );
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

    public function withdraw(
        Request $request,
        Quest $quest,
        QuestOffer $offer,
        ProposalTrustBehaviourService $trustBehaviour,
        ProposalClarificationService $clarifications,
    ): RedirectResponse {
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

        $wasShortlisted = $offer->status === 'shortlisted' || $offer->shortlisted_at !== null;

        $offer->update([
            'status' => 'withdrawn',
            'withdrawn_at' => now(),
            'shortlisted_at' => null,
            'client_pinned_at' => null,
        ]);

        if ($wasShortlisted) {
            $trustBehaviour->recordShortlistedWithdrawal($offer);
        }

        $clarifications->closeForOffer($offer);
        $quest->client?->notify(new ProposalWithdrawnClientNotification($offer));

        $this->recordProposalActivity($request->user(), 'proposal.withdrawn', 'Withdrew proposal', $quest, $offer, $request);

        return redirect()
            ->route('quests.show', $quest)
            ->with('success', $wasShortlisted
                ? __('Proposal withdrawn. Shortlisted withdrawals are logged and may affect your reliability score.')
                : __('Proposal withdrawn. You can submit a fresh proposal while the quest stays open.'));
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

    protected function recordProposalActivity(
        User $user,
        string $action,
        string $title,
        Quest $quest,
        QuestOffer $offer,
        ?Request $request = null,
    ): void {
        app(\App\Services\UserActivity\UserActivityRecorder::class)->recordModel(
            $user,
            $action,
            $title,
            $offer,
            $quest->title.($quest->reference_code ? ' · '.$quest->reference_code : ''),
            ['quest_id' => $quest->id, 'quest_reference' => $quest->reference_code],
            $request,
        );
    }
}
