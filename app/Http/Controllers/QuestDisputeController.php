<?php

namespace App\Http\Controllers;

use App\Enums\QuestDisputeReason;
use App\Enums\DisputeSettlementOfferStatus;
use App\Http\Requests\Disputes\StoreQuestDisputeRequest;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\User;
use App\Services\Disputes\DisputeIntakeFormService;
use App\Services\Disputes\DisputePartyPresenter;
use App\Services\Disputes\DisputeResolutionMatrixService;
use App\Services\Disputes\DisputeResolutionRequestService;
use App\Services\Disputes\DisputePartyWorkflowService;
use App\Services\Disputes\QuestDisputeWorkflowService;
use App\Services\Admin\AdminActivityFeedService;
use App\Support\QuestCommerceUi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestDisputeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $rows = QuestDispute::query()
            ->with(['quest:id,title,slug,uuid,client_id,freelancer_id'])
            ->whereHas('quest', function ($q) use ($user): void {
                $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
            })
            ->latest('updated_at')
            ->limit(80)
            ->get()
            ->map(fn (QuestDispute $d) => [
                'uuid' => $d->uuid,
                'status' => $d->status->value,
                'status_label' => app(DisputePartyPresenter::class)->statusLabel($d),
                'phase' => $d->phase->value,
                'reason' => $d->reason,
                'reason_label' => QuestDisputeReason::tryFrom($d->reason)?->label() ?? $d->reason,
                'category_label' => QuestDisputeReason::tryFrom($d->reason)?->category()->label(),
                'quest_title' => $d->quest?->title,
                'url' => route('disputes.show', $d),
                'updated_at' => $d->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('Disputes/Index', [
            'disputes' => $rows,
            'philosophy' => config('disputes.philosophy', []),
            'workflow_doc_url' => asset('docs/dispute-workflow.md'),
        ]);
    }

    public function create(Request $request, Quest $quest): Response|RedirectResponse
    {
        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user === null || ! $quest->isParty($user)) {
            abort(403);
        }

        $offer = $quest->acceptedOffer;
        if ($offer === null) {
            return redirect()->route('quests.show', $quest)->with('status', __('Accept a proposal before opening a dispute.'));
        }

        $ui = QuestCommerceUi::disputeForQuest($quest, $user);
        if (! $ui['can_open_dispute']) {
            return redirect()->route('quests.show', $quest)->with('status', $ui['dispute_block_reason'] ?? __('You cannot open a dispute right now.'));
        }

        $party = app(QuestDisputeWorkflowService::class)->partyFor($user, $quest) ?? 'client';
        $intakeForm = app(DisputeIntakeFormService::class);

        return Inertia::render('Disputes/Create', [
            'quest' => [
                'title' => $quest->title,
                'route_key' => $quest->getRouteKey(),
            ],
            'offer_id' => $offer->id,
            'party' => $party,
            'intake' => $intakeForm->createPayload($quest, $offer, $party),
            'philosophy' => config('disputes.philosophy', []),
            'policy' => $this->policyPayload($user),
            'store_url' => route('quests.disputes.store', $quest->getRouteKey()),
            'workflow_doc_url' => asset('docs/dispute-workflow.md'),
        ]);
    }

    public function store(StoreQuestDisputeRequest $request, Quest $quest, QuestDisputeWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user === null || ! $quest->isParty($user)) {
            abort(403);
        }

        $offer = $quest->acceptedOffer;
        if ($offer === null) {
            abort(404);
        }

        $reason = QuestDisputeReason::from($request->validated('reason'));
        $party = $workflow->partyFor($user, $quest) ?? 'client';
        $intakeService = app(DisputeIntakeFormService::class);

        $structuredIntake = $intakeService->normalizeIntake(
            $request->validated('structured_intake') ?? [],
            $reason,
            $party,
            $user,
            $quest,
        );

        $dispute = $workflow->open(
            $user,
            $quest,
            $offer,
            $reason,
            $structuredIntake,
            $request->validated('opening_summary'),
        );

        $evidenceFiles = array_values(array_filter($request->file('evidence_files', [])));
        if ($evidenceFiles !== []) {
            $stored = $intakeService->storeEvidenceFiles($dispute->uuid, $evidenceFiles);
            $merged = array_merge($dispute->structured_intake ?? [], ['evidence_files' => $stored]);
            $dispute->update(['structured_intake' => $merged]);
        }
        $quest->loadMissing(['client', 'freelancer', 'questCategory', 'stateModel']);
        app(AdminActivityFeedService::class)->record(
            'disputes',
            'dispute.raised',
            'New dispute raised',
            "{$user->name} raised a {$reason->label()} dispute on {$quest->title}",
            app(AdminActivityFeedService::class)->entities([
                ['type' => 'user', 'id' => $quest->client_id, 'label' => $quest->client?->name],
                ['type' => 'user', 'id' => $quest->freelancer_id, 'label' => $quest->freelancer?->name],
                ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
                ['type' => 'dispute', 'id' => $dispute->id, 'label' => 'Dispute '.$dispute->uuid],
            ]),
            ['type' => $reason->label(), 'category' => $quest->questCategory?->name, 'state' => $quest->stateModel?->name],
            (int) $dispute->disputed_amount_minor,
            $user,
            QuestDispute::class,
            $dispute->id,
            $quest->state_id,
            $quest->local_government_id,
            $quest->quest_category_id,
        );

        return redirect()
            ->route('disputes.show', $dispute)
            ->with('success', __('Dispute file opened. The other party has been emailed and both of you can track progress on the dispute page.'));
    }

    public function show(Request $request, QuestDispute $dispute): Response
    {
        $this->authorize('view', $dispute);

        $dispute->load([
            'quest.client:id,first_name,name,slug,avatar_url,email',
            'quest.freelancer:id,first_name,name,slug,avatar_url,email',
            'openedBy:id,first_name,name,slug,avatar_url',
            'messages.user:id,first_name,name,slug,avatar_url',
            'settlementOffers.offeredBy:id,first_name,name',
            'resolutionRequests.requestedBy:id,first_name,name',
            'events.actor:id,first_name,name',
        ]);

        $user = $request->user();

        return Inertia::render('Disputes/Show', [
            'dispute' => $this->disputePayload($dispute, $user),
            'workflow' => app(DisputePartyWorkflowService::class)->forDispute($dispute, $user),
            'can_participate' => $user?->can('participate', $dispute) ?? false,
            'sla_expectation' => app(\App\Services\Platform\PlatformSlaService::class)->userExpectationForSubject('dispute_resolution', $dispute),
            'philosophy' => config('disputes.philosophy', []),
            'policy' => $this->policyPayload($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function disputePayload(QuestDispute $dispute, ?User $viewer): array
    {
        $quest = $dispute->quest;
        $workflow = app(QuestDisputeWorkflowService::class);
        $partyPresenter = app(DisputePartyPresenter::class);
        $viewerRole = $viewer && $quest ? $workflow->partyFor($viewer, $quest) : null;
        $matrix = app(DisputeResolutionMatrixService::class);
        $resolutionRequests = app(DisputeResolutionRequestService::class);
        $other = $viewer && $quest ? $quest->oppositeParty($viewer) : null;
        $openedByClient = $quest !== null && (int) $dispute->opened_by_user_id === (int) $quest->client_id;

        $dispute->loadMissing('contract');
        $contract = $dispute->contract;

        $settlementOffers = $dispute->settlementOffers->sortByDesc('id')->values()->map(function ($o) use ($dispute, $viewer) {
            $row = [
                'id' => $o->id,
                'client_share_percent' => (int) $o->client_share_percent,
                'note' => $o->note,
                'status' => $o->status->value,
                'offered_by' => [
                    'id' => $o->offeredBy?->id,
                    'name' => $o->offeredBy?->name,
                ],
                'created_at' => $o->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'accept_url' => null,
                'decline_url' => null,
            ];

            if ($viewer !== null
                && $o->status === DisputeSettlementOfferStatus::Pending
                && (int) $o->offered_by_user_id !== (int) $viewer->id) {
                $row['accept_url'] = route('disputes.settlement-offers.accept', [
                    'dispute' => $dispute,
                    'settlement_offer' => $o->id,
                ]);
                $row['decline_url'] = route('disputes.settlement-offers.decline', [
                    'dispute' => $dispute,
                    'settlement_offer' => $o->id,
                ]);
            }

            return $row;
        })->all();

        return [
            'uuid' => $dispute->uuid,
            'reference' => $dispute->displayReference(),
            'status' => $dispute->status->value,
            'status_label' => $partyPresenter->statusLabel($dispute),
            'phase' => $dispute->phase->value,
            'reason' => $dispute->reason,
            'reason_label' => QuestDisputeReason::tryFrom($dispute->reason)?->label() ?? $dispute->reason,
            'category_label' => QuestDisputeReason::tryFrom($dispute->reason)?->category()->label()
                ?? ($dispute->structured_intake['category_label'] ?? null),
            'intake_labels' => app(DisputeIntakeFormService::class)->displayLabels(),
            'structured_intake' => $dispute->structured_intake ?? [],
            'opening_summary' => $dispute->opening_summary,
            'disputed_amount_minor' => (int) $dispute->disputed_amount_minor,
            'response_required_by' => $dispute->response_required_by?->timezone('Africa/Lagos')->toIso8601String(),
            'ruling_required_by' => $dispute->ruling_required_by?->timezone('Africa/Lagos')->toIso8601String(),
            'awaiting_user_id' => $dispute->awaiting_user_id,
            'awaiting_viewer' => $viewer !== null && (int) $dispute->awaiting_user_id === (int) $viewer->id,
            'resolution_outcome' => $dispute->resolution_outcome,
            'resolution_outcome_label' => app(\App\Services\Disputes\DisputeResolutionOutcomeLabelService::class)->label($dispute->resolution_outcome),
            'party_self_resolved' => in_array((string) $dispute->resolution_outcome, ['settlement_accepted', 'mutual_resolve'], true),
            'final_client_share_percent' => $dispute->final_client_share_percent,
            'client_agrees_resolve_at' => $dispute->client_agrees_resolve_at?->timezone('Africa/Lagos')->toIso8601String(),
            'freelancer_agrees_resolve_at' => $dispute->freelancer_agrees_resolve_at?->timezone('Africa/Lagos')->toIso8601String(),
            'escalated_at' => $dispute->escalated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'viewer_role' => $viewerRole,
            'opened_by' => [
                'id' => $dispute->openedBy?->id,
                'name' => $dispute->openedBy?->name,
                'first_name' => $dispute->openedBy?->first_name,
                'party' => $openedByClient ? 'client' : 'freelancer',
            ],
            'other_party' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'first_name' => $other->first_name,
                'slug' => $other->slug,
                'avatar_url' => $other->avatar_url,
                'role' => $viewerRole === 'client' ? 'freelancer' : 'client',
            ] : null,
            'quest' => [
                'title' => $quest?->title,
                'route_key' => $quest?->getRouteKey(),
            ],
            'contract' => $contract ? [
                'reference_code' => $contract->reference_code,
                'url' => route('contracts.show', $contract->reference_code),
            ] : null,
            'contract_disputes' => $contract
                ? app(\App\Services\Disputes\DisputeManagementPresenter::class)->contractDisputeHistory($contract)
                : [],
            'messages' => $partyPresenter->visibleMessages($dispute->messages, $dispute),
            'settlement_offers' => $settlementOffers,
            'events' => $partyPresenter->visibleEvents($dispute->events),
            'urls' => [
                'message' => route('disputes.messages.store', $dispute),
                'settlement' => route('disputes.settlement-offers.store', $dispute),
                'mutual_resolve' => route('disputes.mutual-resolve.store', $dispute),
                'resolution_request' => route('disputes.resolution-requests.store', $dispute),
                'negotiation_propose' => route('disputes.negotiation.propose', $dispute),
                'negotiation_acknowledge_binding' => route('disputes.negotiation.acknowledge_binding', $dispute),
                'appeal_store' => route('disputes.appeals.store', $dispute),
            ],
            'resolution_options' => $viewerRole ? $matrix->optionsForActor($viewerRole) : [],
            'resolution_requests' => $resolutionRequests->listForDispute($dispute),
            'negotiation' => app(\App\Services\Disputes\DisputeNegotiationService::class)->payloadForParty($dispute, $viewer),
            'appeal' => $viewer ? app(\App\Services\Disputes\DisputeAppealService::class)->payloadForParty($dispute, $viewer) : null,
        ];
    }

    /**
     * @return array<string, int|float|bool>
     */
    protected function policyPayload(?User $viewer = null): array
    {
        $reviewThreshold = (int) config('disputes.account_review_after_dispute_count', 3);
        $userDisputeCount = 0;

        if ($viewer !== null) {
            $userDisputeCount = QuestDispute::query()
                ->where(function ($q) use ($viewer): void {
                    $q->where('opened_by_user_id', $viewer->id)
                        ->orWhereHas('quest', function ($quest) use ($viewer): void {
                            $quest->where('client_id', $viewer->id)
                                ->orWhere('freelancer_id', $viewer->id);
                        });
                })
                ->count();
        }

        return [
            'minimum_disputed_amount_minor' => (int) config('disputes.minimum_disputed_amount_minor', 500_000),
            'max_days_after_completion' => (int) config('disputes.max_days_after_completion_to_open', 14),
            'self_resolution_hours' => (int) config('disputes.self_resolution_response_hours', 48),
            'formal_ruling_hours' => (int) config('disputes.formal_no_response_ruling_hours', 72),
            'platform_fee_percent' => (float) config('disputes.platform_resolution_fee_percent', 0),
            'resolution_fee_charged' => false,
            'max_appeals' => (int) config('disputes.max_appeals_per_dispute', 1),
            'review_after_dispute_count' => $reviewThreshold,
            'user_dispute_count' => $userDisputeCount,
            'user_near_review_threshold' => $userDisputeCount >= max(1, $reviewThreshold - 1),
        ];
    }
}
