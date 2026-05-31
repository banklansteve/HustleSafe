<?php

namespace App\Services\Contracts;

use App\Enums\ContractStatus;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\User;
use App\Support\EscrowReleasePolicy;
use Illuminate\Support\Collection;

class ContractPresentationService
{
    public function __construct(private readonly ContractEventLogger $events) {}

    /**
     * @return array<string, mixed>
     */
    public function showPayload(QuestContract $contract, User $viewer, bool $adminView = false): array
    {
        $contract->loadMissing([
            'quest',
            'offer',
            'client:id,name,username,slug,avatar_url',
            'freelancer:id,name,username,slug,avatar_url',
            'deliverables',
            'milestones',
            'amendments.requester:id,name',
            'amendments.responder:id,name',
            'activeDispute',
        ]);

        $this->events->log($contract, 'contract.viewed', $viewer);

        $isClient = (int) $viewer->id === (int) $contract->client_id;
        $isFreelancer = (int) $viewer->id === (int) $contract->freelancer_id;
        $terms = $contract->effectiveTerms();
        $quest = $contract->quest;

        return [
            'contract' => $this->contractRow($contract, $terms, $quest, $adminView),
            'timeline_stages' => $this->lifecycleStages($contract, $quest),
            'role' => [
                'is_client' => $isClient,
                'is_freelancer' => $isFreelancer,
                'is_admin' => $adminView,
            ],
            'permissions' => [
                'can_request_amendment' => $contract->status === ContractStatus::Active
                    && $contract->amendment_count < ContractAmendmentService::MAX_AMENDMENTS
                    && ! $contract->amendments()->where('status', 'pending')->exists()
                    && ($isClient || $isFreelancer),
                'can_respond_amendment' => $this->pendingAmendmentFor($contract, $viewer) !== null,
                'can_download_pdf' => true,
            ],
            'pending_amendment' => $this->pendingAmendmentPayload($contract, $viewer),
            'admin_panel' => $adminView ? $this->adminPanel($contract) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function indexRows(User $user): array
    {
        return QuestContract::query()
            ->with(['quest:id,title,slug,uuid', 'client:id,name', 'freelancer:id,name'])
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->latest('generated_at')
            ->limit(200)
            ->get()
            ->map(fn (QuestContract $c) => [
                'reference_code' => $c->reference_code,
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'quest_title' => $c->quest?->title,
                'quest_route_key' => $c->quest?->getRouteKey(),
                'counterparty_name' => (int) $user->id === (int) $c->client_id
                    ? $c->freelancer?->name
                    : $c->client?->name,
                'total_label' => $c->financial_snapshot['total_label'] ?? null,
                'generated_at' => $c->generated_at?->timezone('Africa/Lagos')->toIso8601String(),
                'show_url' => route('contracts.show', $c->reference_code),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $terms
     * @return array<string, mixed>
     */
    private function contractRow(QuestContract $contract, array $terms, ?Quest $quest, bool $adminView): array
    {
        $financial = $terms['financial'] ?? $contract->financial_snapshot;
        $timeline = $terms['timeline'] ?? $contract->timeline_snapshot;

        $amendments = $contract->amendments
            ->when(! $adminView, fn (Collection $rows) => $rows->where('status', 'accepted'))
            ->values()
            ->map(fn ($a) => [
                'id' => $a->id,
                'amendment_number' => $a->amendment_number,
                'type' => $a->amendment_type->value,
                'type_label' => $a->amendment_type->label(),
                'status' => $a->status,
                'description' => $a->description,
                'reason' => $a->reason,
                'original_value' => $a->original_value,
                'new_value' => $a->new_value,
                'requester_name' => $a->requester?->name,
                'response_note' => $adminView ? $a->response_note : ($a->status === 'declined' ? null : $a->response_note),
                'responded_at' => $a->responded_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])
            ->all();

        return [
            'reference_code' => $contract->reference_code,
            'status' => $contract->status->value,
            'status_label' => $contract->status->label(),
            'generated_at' => $contract->generated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'activated_at' => $contract->activated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'completed_at' => $contract->completed_at?->timezone('Africa/Lagos')->toIso8601String(),
            'parties' => $contract->parties_snapshot,
            'quest' => $terms['quest'] ?? $contract->quest_snapshot,
            'deliverables' => $contract->deliverables->map(fn ($d) => [
                'title' => $d->title,
                'description' => $d->description,
            ])->all(),
            'milestones' => $contract->milestones->map(fn ($m) => [
                'name' => $m->name,
                'deliverable_reference' => $m->deliverable_reference,
                'value_label' => '₦'.number_format($m->value_minor / 100, 0),
                'deadline_date' => $m->deadline_date?->toDateString(),
            ])->all(),
            'financial' => $financial,
            'timeline' => $timeline,
            'revision_policy' => $terms['revision_policy'] ?? $contract->revision_policy_snapshot,
            'revisions_used' => $contract->revisions_used,
            'revisions_included' => $contract->revisions_included,
            'platform_terms' => $contract->platform_terms_snapshot,
            'signatures' => $contract->signatures_snapshot,
            'amendments' => $amendments,
            'amendment_count' => $contract->amendment_count,
            'amendment_limit' => ContractAmendmentService::MAX_AMENDMENTS,
            'dispute_url' => $contract->activeDispute
                ? route('disputes.show', $contract->activeDispute)
                : null,
            'quest_url' => $quest ? route('quests.show', $quest->getRouteKey()) : null,
            'escrow_expires_at' => $contract->escrow_expires_at?->timezone('Africa/Lagos')->toIso8601String(),
            'delivery_countdown' => $this->deliveryCountdown($contract),
            'dispute_window' => $this->disputeWindow($quest, $contract),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lifecycleStages(QuestContract $contract, ?Quest $quest): array
    {
        $stages = [
            ['key' => 'generated', 'label' => 'Contract Generated', 'at' => $contract->generated_at],
            ['key' => 'escrow_funded', 'label' => 'Escrow Funded', 'at' => $contract->escrow_funded_at ?? $contract->activated_at],
            ['key' => 'work_started', 'label' => 'Work Started', 'at' => $contract->activated_at],
            ['key' => 'delivery_submitted', 'label' => 'Delivery Submitted', 'at' => $quest?->delivery_acknowledged_at],
            ['key' => 'under_review', 'label' => 'Under Review', 'at' => $quest?->delivery_acknowledged_at],
            ['key' => 'completed', 'label' => $contract->status === ContractStatus::Disputed ? 'Disputed' : 'Completed', 'at' => $contract->completed_at],
        ];

        $currentIndex = match ($contract->status) {
            ContractStatus::PendingEscrow => 0,
            ContractStatus::Active, ContractStatus::AmendmentPending => $quest?->delivery_acknowledged_at ? 4 : ($contract->activated_at ? 2 : 1),
            ContractStatus::Disputed => 5,
            ContractStatus::Completed => 5,
            ContractStatus::Cancelled => 0,
        };

        return collect($stages)->map(function (array $stage, int $index) use ($currentIndex, $contract) {
            $at = $stage['at'];

            return [
                'key' => $stage['key'],
                'label' => $stage['label'],
                'completed' => $at !== null,
                'current' => $index === $currentIndex && $contract->status !== ContractStatus::Completed && $contract->status !== ContractStatus::Cancelled,
                'at_label' => $at ? $at->timezone('Africa/Lagos')->format('D, j M Y · g:i A') : null,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingAmendmentPayload(QuestContract $contract, User $viewer): ?array
    {
        $amendment = $this->pendingAmendmentFor($contract, $viewer);
        if ($amendment === null) {
            return null;
        }

        return [
            'id' => $amendment->id,
            'amendment_number' => $amendment->amendment_number,
            'type' => $amendment->amendment_type->value,
            'type_label' => $amendment->amendment_type->label(),
            'description' => $amendment->description,
            'reason' => $amendment->reason,
            'original_value' => $amendment->original_value,
            'new_value' => $amendment->new_value,
            'requester_name' => $amendment->requester?->name,
        ];
    }

    private function pendingAmendmentFor(QuestContract $contract, User $viewer): ?\App\Models\QuestContractAmendment
    {
        $pending = $contract->amendments->firstWhere('status', 'pending');
        if ($pending === null || (int) $pending->requested_by_user_id === (int) $viewer->id) {
            return null;
        }

        return $contract->isParty($viewer) ? $pending : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryCountdown(QuestContract $contract): array
    {
        if (! $contract->agreed_delivery_date) {
            return ['active' => false];
        }

        $deadline = $contract->agreed_delivery_date->copy()->endOfDay();
        $seconds = max(0, now()->diffInSeconds($deadline, false));

        return [
            'active' => $contract->status === ContractStatus::Active,
            'deadline_label' => $deadline->timezone('Africa/Lagos')->format('j M Y'),
            'seconds_remaining' => $seconds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function disputeWindow(?Quest $quest, QuestContract $contract): array
    {
        if ($quest === null || ! $quest->delivery_acknowledged_at) {
            return ['active' => false];
        }

        $policy = EscrowReleasePolicy::uiPayload($quest, null);

        return [
            'active' => true,
            'seconds_until_release' => (int) ($policy['seconds_until_release'] ?? 0),
            'release_eligible_label' => $policy['release_eligible_label'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPanel(QuestContract $contract): array
    {
        $contract->loadMissing(['events.user:id,name,email']);

        return [
            'parties_forensics' => [
                'client' => [
                    'ip' => $contract->parties_snapshot['client']['confirmation_ip'] ?? null,
                    'user_agent' => $contract->parties_snapshot['client']['confirmation_user_agent'] ?? null,
                ],
                'freelancer' => [
                    'ip' => $contract->parties_snapshot['freelancer']['confirmation_ip'] ?? null,
                    'user_agent' => $contract->parties_snapshot['freelancer']['confirmation_user_agent'] ?? null,
                ],
            ],
            'event_log' => $contract->events->take(100)->map(fn ($e) => [
                'event_type' => $e->event_type,
                'actor' => $e->user?->name ?? 'System',
                'at_label' => $e->created_at?->timezone('Africa/Lagos')->format('D, j M Y · g:i A'),
                'properties' => $e->properties,
            ])->all(),
            'client_profile_url' => route('admin.users.index', ['q' => $contract->client?->email]),
            'freelancer_profile_url' => route('admin.users.index', ['q' => $contract->freelancer?->email]),
            'flagged_for_review' => $contract->flagged_for_review,
            'flagged_for_review_reason' => $contract->flagged_for_review_reason,
            'can_flag_for_review' => auth()->user()?->role?->slug === 'super_admin',
        ];
    }
}
