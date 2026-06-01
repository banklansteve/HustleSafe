<?php

namespace App\Services\Contracts;

use App\Enums\ContractStatus;
use App\Enums\DeliveryExtensionReasonCategory;
use App\Enums\DeliveryExtensionStatus;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\User;
use App\Services\Admin\FinancialControlCentreService;
use App\Services\QuestEngagementLifecycleService;
use App\Support\EscrowAutoReleasePolicy;
use App\Support\EscrowReleasePolicy;
use App\Support\NgnMoney;
use Illuminate\Support\Collection;

class ContractPresentationService
{
    public function __construct(
        private readonly ContractEventLogger $events,
        private readonly ContractDeliveryExtensionService $extensions,
    ) {}

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
            'pendingExtension.requester:id,name',
            'pendingExtension.scopeChangeMessage.user:id,name',
            'deliveryExtensions',
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
            'delivery_extension' => $this->deliveryExtensionPayload($contract, $viewer, $isFreelancer, $isClient),
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
        $timeline = array_merge(
            $terms['timeline'] ?? $contract->timeline_snapshot ?? [],
            EscrowAutoReleasePolicy::timelineSnapshot(),
        );

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
            'dispute_window' => $this->autoReleaseWindow($quest, $contract),
            'delivery_timeline' => $this->deliveryTimeline($contract, $terms),
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

        if ($contract->deadline_clock_paused_at !== null) {
            return [
                'active' => true,
                'paused' => true,
                'deadline_label' => $contract->agreed_delivery_date->timezone('Africa/Lagos')->format('j M Y'),
                'seconds_remaining' => null,
                'pause_reason' => __('Paused while a delivery extension request is pending.'),
            ];
        }

        $deadline = $contract->agreed_delivery_date->copy()->endOfDay();
        $seconds = max(0, now()->diffInSeconds($deadline, false));

        return [
            'active' => $contract->status === ContractStatus::Active,
            'paused' => false,
            'deadline_label' => $deadline->timezone('Africa/Lagos')->format('j M Y'),
            'seconds_remaining' => $seconds,
        ];
    }

    /**
     * @param  array<string, mixed>  $terms
     * @return array<string, mixed>
     */
    private function deliveryTimeline(QuestContract $contract, array $terms): array
    {
        $timeline = $terms['timeline'] ?? [];
        $extensions = $timeline['extensions'] ?? [];
        $original = $contract->original_agreed_delivery_date
            ?? ($extensions[0]['original_date'] ?? null)
            ?? $contract->agreed_delivery_date?->toDateString();

        $count = (int) $contract->delivery_extension_count;
        $limit = ContractDeliveryExtensionService::MAX_EXTENSIONS;

        return [
            'original_deadline' => $original,
            'original_deadline_label' => $original ? \Carbon\Carbon::parse($original)->format('j M Y') : null,
            'current_deadline' => $contract->agreed_delivery_date?->toDateString(),
            'current_deadline_label' => $contract->agreed_delivery_date?->format('j M Y'),
            'has_extension' => $count > 0,
            'extension_count' => $count,
            'extension_limit' => $limit,
            'extension_badge' => $count > 0
                ? ($count >= $limit
                    ? __('Extension :count of :limit — No further extensions available', ['count' => $count, 'limit' => $limit])
                    : __('Extension :count of :limit used', ['count' => $count, 'limit' => $limit]))
                : null,
            'history' => collect($extensions)->map(fn (array $row) => [
                'extension_number' => $row['extension_number'] ?? null,
                'original_label' => $row['original_label'] ?? null,
                'new_label' => $row['new_label'] ?? null,
                'reason_label' => $row['reason_label'] ?? null,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryExtensionPayload(QuestContract $contract, User $viewer, bool $isFreelancer, bool $isClient): array
    {
        $button = $this->extensions->freelancerButtonState($contract);
        $pending = $contract->pendingExtension;

        $payload = [
            'freelancer_button' => $button,
            'reason_categories' => DeliveryExtensionReasonCategory::options(),
            'max_extension_days' => ContractDeliveryExtensionService::MAX_EXTENSION_DAYS,
            'messages_url' => route('contracts.extensions.messages', $contract->reference_code),
        ];

        if ($pending === null) {
            $payload['pending'] = null;
            $payload['pending_counter'] = null;

            return $payload;
        }

        $pendingRow = [
            'id' => $pending->id,
            'extension_number' => $pending->extension_number,
            'status' => $pending->status->value,
            'status_label' => $pending->status->label(),
            'reason_category' => $pending->reason_category->value,
            'reason_label' => $pending->reason_category->label(),
            'explanation' => $pending->explanation,
            'original_delivery_label' => $pending->original_delivery_date->format('j M Y'),
            'proposed_delivery_label' => $pending->proposed_delivery_date->format('j M Y'),
            'progress_note' => $pending->progress_note,
            'progress_attachments' => $pending->progress_attachments ?? [],
            'scope_change_message' => $pending->scopeChangeMessage ? [
                'id' => $pending->scopeChangeMessage->id,
                'body' => $pending->scopeChangeMessage->is_redacted
                    ? ($pending->scopeChangeMessage->redaction_label ?? '[redacted]')
                    : $pending->scopeChangeMessage->body,
                'author' => $pending->scopeChangeMessage->user?->name,
            ] : null,
            'client_attributed_delay' => $pending->client_attributed_delay,
        ];

        if ($pending->status === DeliveryExtensionStatus::PendingClient) {
            $pendingRow['client_seconds_remaining'] = (int) max(0, now()->diffInSeconds($pending->client_response_deadline_at, false));
            $pendingRow['client_deadline_label'] = $pending->client_response_deadline_at->timezone(config('app.timezone'))->format('j M Y, H:i');
            $payload['pending'] = $isClient ? $pendingRow : array_merge($pendingRow, [
                'client_seconds_remaining' => $pendingRow['client_seconds_remaining'],
            ]);
            $payload['pending_counter'] = null;

            return $payload;
        }

        if ($pending->status === DeliveryExtensionStatus::CounterProposed) {
            $pendingRow['counter_proposed_label'] = $pending->counter_proposed_date?->format('j M Y');
            $pendingRow['freelancer_seconds_remaining'] = (int) max(0, now()->diffInSeconds($pending->counter_response_deadline_at, false));
            $payload['pending'] = $isClient ? $pendingRow : null;
            $payload['pending_counter'] = $isFreelancer ? $pendingRow : null;

            return $payload;
        }

        $payload['pending'] = null;
        $payload['pending_counter'] = null;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function autoReleaseWindow(?Quest $quest, QuestContract $contract): array
    {
        if ($quest === null || $contract->status !== ContractStatus::Active) {
            return ['active' => false];
        }

        if ($quest->completed_at !== null || $quest->funds_released_at !== null) {
            return ['active' => false];
        }

        $contract->loadMissing('pendingExtension');
        if ($contract->pending_extension_id !== null || $contract->deadline_clock_paused_at !== null) {
            return ['active' => false];
        }

        $due = app(QuestEngagementLifecycleService::class)->expectedCompletionAt($quest);
        if ($due === null && $contract->agreed_delivery_date !== null) {
            $due = $contract->agreed_delivery_date->copy()->endOfDay();
        }

        if ($due === null || now()->lt($due)) {
            return ['active' => false];
        }

        $releaseAt = EscrowAutoReleasePolicy::releaseAt($due);

        return [
            'active' => true,
            'seconds_until_release' => (int) max(0, now()->diffInSeconds($releaseAt, false)),
            'release_eligible_label' => $releaseAt->timezone(config('app.timezone'))->format('j M Y, H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPanel(QuestContract $contract): array
    {
        $contract->loadMissing(['events.user:id,name,email', 'deliveryExtensions']);

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
            'escrow_controls' => $this->escrowControls($contract),
            'client_attributed_extensions' => $contract->deliveryExtensions
                ->where('client_attributed_delay', true)
                ->map(fn ($e) => [
                    'extension_number' => $e->extension_number,
                    'reason_label' => $e->reason_category->label(),
                    'submitted_at' => $e->submitted_at?->timezone('Africa/Lagos')->format('D, j M Y · g:i A'),
                ])->values()->all(),
        ];
    }

    /**
     * Super Admin escrow override — pause auto-release, freeze, or release funds from the contract page.
     *
     * @return array<string, mixed>|null
     */
    private function escrowControls(QuestContract $contract): ?array
    {
        $viewer = auth()->user();
        if ($viewer?->role?->slug !== 'super_admin') {
            return null;
        }

        $quest = $contract->quest;
        if ($quest === null) {
            return null;
        }

        $quest->loadMissing('paymentEscrow');
        $finance = app(FinancialControlCentreService::class);
        $ledger = $finance->escrowLedger($quest);
        $releasePolicy = EscrowReleasePolicy::uiPayload($quest, $viewer);
        $disputeWindow = $this->disputeWindow($contract, $quest);

        return [
            'quest_id' => $quest->id,
            'quest_reference' => $quest->reference_code,
            'escrow_status' => $quest->escrow_status,
            'held_label' => $ledger['controls']['held'] ?? NgnMoney::format(0),
            'held_minor' => (int) ($ledger['controls']['held_minor'] ?? 0),
            'dispute_opened' => (bool) $quest->dispute_opened,
            'contract_status' => $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status,
            'frozen_at_label' => $quest->escrow_frozen_at?->timezone('Africa/Lagos')->format('D, j M Y · g:i A'),
            'freeze_reason' => $quest->escrow_freeze_reason,
            'hold_reason' => $quest->release_hold_reason,
            'hold_until_label' => $quest->release_hold_until?->timezone('Africa/Lagos')->format('D, j M Y · g:i A'),
            'auto_release_countdown_active' => $disputeWindow['active'] ?? false,
            'auto_release_label' => $disputeWindow['release_eligible_label'] ?? null,
            'auto_release_plain_english' => $contract->timeline_snapshot['auto_release_plain_english'] ?? EscrowAutoReleasePolicy::plainEnglishWithReminders(),
            'release_policy' => $releasePolicy,
            'requires_authorization' => EscrowReleasePolicy::requiresSuperAdminAuthorization($quest),
            'has_authorization' => EscrowReleasePolicy::hasSuperAdminAuthorization($quest),
            'high_value_threshold' => NgnMoney::format(EscrowReleasePolicy::highValueAuthorizationMinor()),
            'routes' => [
                'ledger' => route('admin.financial.escrows.ledger', $quest),
                'action' => route('admin.financial.escrows.action', $quest),
                'hold_auto_release' => route('admin.quests.release.hold', $quest),
                'lift_auto_release_hold' => route('admin.quests.release.lift-hold', $quest),
                'authorize_release' => route('admin.quests.release.authorize', $quest),
            ],
            'financial_centre_url' => route('admin.financial.index', ['tab' => 'escrow']),
            'documentation_url' => route('admin.documentation.guide', ['topic' => 'payments-escrow']),
        ];
    }
}
