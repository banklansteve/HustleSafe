<?php

namespace App\Http\Controllers;

use App\Enums\QuestDisputeReason;
use App\Enums\DisputeSettlementOfferStatus;
use App\Http\Requests\Disputes\StoreQuestDisputeRequest;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\User;
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
                'phase' => $d->phase->value,
                'reason' => $d->reason,
                'reason_label' => QuestDisputeReason::tryFrom($d->reason)?->label() ?? $d->reason,
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
        $reasons = QuestDisputeReason::forParty($party);

        return Inertia::render('Disputes/Create', [
            'quest' => [
                'title' => $quest->title,
                'route_key' => $quest->getRouteKey(),
            ],
            'offer_id' => $offer->id,
            'party' => $party,
            'reason_options' => collect($reasons)->map(fn (QuestDisputeReason $r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ])->values()->all(),
            'philosophy' => config('disputes.philosophy', []),
            'policy' => [
                'minimum_disputed_amount_minor' => (int) config('disputes.minimum_disputed_amount_minor', 500_000),
                'max_days_after_completion' => (int) config('disputes.max_days_after_completion_to_open', 14),
                'self_resolution_hours' => (int) config('disputes.self_resolution_response_hours', 48),
                'formal_ruling_hours' => (int) config('disputes.formal_no_response_ruling_hours', 72),
                'platform_fee_percent' => (float) config('disputes.platform_resolution_fee_percent', 2),
                'max_appeals' => (int) config('disputes.max_appeals_per_dispute', 1),
                'suspension_threshold' => (int) config('disputes.account_suspension_review_after_lost_disputes', 3),
            ],
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

        $dispute = $workflow->open(
            $user,
            $quest,
            $offer,
            $reason,
            $request->validated('structured_intake') ?? [],
            $request->validated('opening_summary'),
        );
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
            ->with('success', __('Dispute file created — both parties were notified with next steps.'));
    }

    public function show(Request $request, QuestDispute $dispute): Response
    {
        $this->authorize('view', $dispute);

        $dispute->load([
            'quest.client:id,first_name,name,slug',
            'quest.freelancer:id,first_name,name,slug',
            'openedBy:id,first_name,name',
            'messages.user:id,first_name,name,slug,avatar_url',
            'settlementOffers.offeredBy:id,first_name,name',
            'events.actor:id,first_name,name',
        ]);

        $user = $request->user();

        return Inertia::render('Disputes/Show', [
            'dispute' => $this->disputePayload($dispute, $user),
            'can_participate' => $user?->can('participate', $dispute) ?? false,
            'philosophy' => config('disputes.philosophy', []),
            'policy' => [
                'minimum_disputed_amount_minor' => (int) config('disputes.minimum_disputed_amount_minor', 500_000),
                'max_days_after_completion' => (int) config('disputes.max_days_after_completion_to_open', 14),
                'self_resolution_hours' => (int) config('disputes.self_resolution_response_hours', 48),
                'formal_ruling_hours' => (int) config('disputes.formal_no_response_ruling_hours', 72),
                'platform_fee_percent' => (float) config('disputes.platform_resolution_fee_percent', 2),
                'max_appeals' => (int) config('disputes.max_appeals_per_dispute', 1),
                'suspension_threshold' => (int) config('disputes.account_suspension_review_after_lost_disputes', 3),
            ],
            'workflow_doc_url' => asset('docs/dispute-workflow.md'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function disputePayload(QuestDispute $dispute, ?User $viewer): array
    {
        $quest = $dispute->quest;

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
            'status' => $dispute->status->value,
            'phase' => $dispute->phase->value,
            'reason' => $dispute->reason,
            'reason_label' => QuestDisputeReason::tryFrom($dispute->reason)?->label() ?? $dispute->reason,
            'structured_intake' => $dispute->structured_intake ?? [],
            'opening_summary' => $dispute->opening_summary,
            'disputed_amount_minor' => (int) $dispute->disputed_amount_minor,
            'response_required_by' => $dispute->response_required_by?->timezone('Africa/Lagos')->toIso8601String(),
            'ruling_required_by' => $dispute->ruling_required_by?->timezone('Africa/Lagos')->toIso8601String(),
            'awaiting_user_id' => $dispute->awaiting_user_id,
            'resolution_outcome' => $dispute->resolution_outcome,
            'final_client_share_percent' => $dispute->final_client_share_percent,
            'client_agrees_resolve_at' => $dispute->client_agrees_resolve_at?->timezone('Africa/Lagos')->toIso8601String(),
            'freelancer_agrees_resolve_at' => $dispute->freelancer_agrees_resolve_at?->timezone('Africa/Lagos')->toIso8601String(),
            'escalated_at' => $dispute->escalated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'quest' => [
                'title' => $quest?->title,
                'route_key' => $quest?->getRouteKey(),
            ],
            'messages' => $dispute->messages->map(fn ($m) => [
                'id' => $m->id,
                'kind' => $m->kind->value,
                'body' => $m->body,
                'structured_key' => $m->structured_key,
                'structured_payload' => $m->structured_payload,
                'created_at' => $m->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'user' => $m->user ? [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'first_name' => $m->user->first_name,
                ] : null,
            ])->values()->all(),
            'settlement_offers' => $settlementOffers,
            'events' => $dispute->events->map(fn ($e) => [
                'action' => $e->action,
                'properties' => $e->properties ?? [],
                'created_at' => $e->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'actor' => $e->actor ? [
                    'id' => $e->actor->id,
                    'name' => $e->actor->name,
                ] : null,
            ])->values()->all(),
            'urls' => [
                'message' => route('disputes.messages.store', $dispute),
                'settlement' => route('disputes.settlement-offers.store', $dispute),
                'mutual_resolve' => route('disputes.mutual-resolve.store', $dispute),
            ],
        ];
    }
}
