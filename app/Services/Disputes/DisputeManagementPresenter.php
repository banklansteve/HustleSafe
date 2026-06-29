<?php

namespace App\Services\Disputes;

use App\Enums\DisputeMessageKind;
use App\Enums\QuestDisputeReason;
use App\Models\DisputeAssessment;
use App\Models\DisputeEvent;
use App\Models\DisputeMessage;
use App\Models\QuestContract;
use App\Enums\QuestDisputeManagementStatus;
use App\Models\QuestDispute;
use App\Models\User;

class DisputeManagementPresenter
{
    private const PARTY_SELF_OUTCOMES = ['settlement_accepted', 'mutual_resolve'];

    private const SELF_RESOLUTION_EVENT_ACTIONS = [
        'dispute.settlement_offered',
        'dispute.settlement_accepted',
        'dispute.settlement_declined',
        'dispute.mutual_resolve_ack',
        'dispute.party_self_resolved',
    ];

    public function __construct(
        private readonly DisputeWorkflowChecklistService $workflowChecklist,
    ) {}

    public function row(QuestDispute $dispute, bool $expanded = false, ?User $viewer = null): array
    {
        $reason = QuestDisputeReason::tryFrom((string) $dispute->reason);
        $contract = $dispute->relationLoaded('contract')
            ? $dispute->contract
            : QuestContract::query()
                ->where('quest_id', $dispute->quest_id)
                ->where('quest_offer_id', $dispute->quest_offer_id)
                ->first();

        $contractPayload = $this->contractPayload($contract, $viewer);

        $base = [
            'id' => $dispute->id,
            'uuid' => $dispute->uuid,
            'reference' => $dispute->displayReference(),
            'status' => $dispute->status?->value ?? (string) $dispute->status,
            'management_status' => $dispute->management_status?->value ?? (string) $dispute->management_status,
            'management_status_label' => $dispute->management_status?->label() ?? (string) $dispute->management_status,
            'management_badge_tone' => $dispute->management_status?->badgeTone() ?? 'slate',
            'phase' => $dispute->phase?->value ?? (string) $dispute->phase,
            'tier' => $dispute->tier,
            'severity' => $dispute->severity,
            'quest' => $dispute->quest?->title,
            'quest_reference' => $dispute->quest?->reference_code,
            'contract_reference' => $contract?->reference_code,
            'contract' => $contractPayload,
            'quest_id' => $dispute->quest_id,
            'category_label' => $reason?->category()->label(),
            'reason_label' => $reason?->label() ?? $dispute->reason,
            'opened_by' => $dispute->openedBy?->name,
            'assigned_staff' => $dispute->assignedStaff?->name,
            'assigned_staff_id' => $dispute->assigned_staff_id,
            'disputed_amount_minor' => (int) $dispute->disputed_amount_minor,
            'days_open' => $dispute->created_at?->diffInDays(now()) ?? 0,
            'needs_staff_action' => data_get($dispute->workflow_state, 'pending_staff_action.type') !== null,
            'created_at' => $dispute->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'ready_for_decision_at' => $dispute->ready_for_decision_at?->timezone('Africa/Lagos')->toIso8601String(),
            'resolution_outcome' => $dispute->resolution_outcome,
            'resolution_outcome_label' => app(DisputeResolutionOutcomeLabelService::class)->label($dispute->resolution_outcome),
            'party_self_resolved' => $this->isPartySelfResolved($dispute),
            'needs_admin_acknowledgment' => $this->needsAdminAcknowledgment($dispute),
        ];

        if (! $expanded) {
            return $base;
        }

        return array_merge($base, [
            'reason' => $dispute->reason,
            'opening_summary' => $dispute->opening_summary,
            'structured_intake' => $dispute->structured_intake ?? [],
            'final_client_share_percent' => $dispute->final_client_share_percent,
            'management_resolved_at' => $dispute->management_resolved_at?->timezone('Africa/Lagos')->toIso8601String(),
            'staff_claimed_at' => $dispute->staff_claimed_at?->timezone('Africa/Lagos')->toIso8601String(),
            'staff_acknowledged_at' => $dispute->staff_acknowledged_at?->timezone('Africa/Lagos')->toIso8601String(),
            'held_at' => $dispute->held_at?->timezone('Africa/Lagos')->toIso8601String(),
            'hold_reason' => $dispute->hold_reason,
            'reassignment_count' => (int) $dispute->reassignment_count,
            'super_admin_decision_notes' => $dispute->super_admin_decision_notes,
            'sanction_payload' => $dispute->sanction_payload ?? [],
            'outcome_action' => $dispute->outcome_action,
            'extended_deadline_at' => $dispute->extended_deadline_at?->timezone('Africa/Lagos')->toIso8601String(),
            'chargeback_risk_flagged_at' => $dispute->chargeback_risk_flagged_at?->timezone('Africa/Lagos')->toIso8601String(),
            'pattern_investigation_at' => $dispute->pattern_investigation_at?->timezone('Africa/Lagos')->toIso8601String(),
            'report_generated_at' => $dispute->report_generated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'sealed_at' => $dispute->sealed_at?->timezone('Africa/Lagos')->toIso8601String(),
            'has_report' => $dispute->report_path !== null,
            'appeal_window_ends_at' => $dispute->appeal_window_ends_at?->timezone('Africa/Lagos')->toIso8601String(),
            'finalized_at' => $dispute->finalized_at?->timezone('Africa/Lagos')->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(QuestDispute $dispute, User $viewer, DisputeManagementPermissionService $permissions): array
    {
        $dispute->load([
            'quest.client:id,name,email,first_name',
            'quest.freelancer:id,name,email,first_name',
            'contract',
            'openedBy:id,name,email,first_name',
            'assignedStaff:id,name,email',
            'superAdminDecidedBy:id,name,email',
            'messages.user:id,name,email',
            'events.actor:id,name,email',
            'assessments.staff:id,name,email',
            'mediationSessions.openedBy:id,name,email',
            'precedents.createdBy:id,name,email',
        ]);

        $quest = $dispute->quest;
        $openedByClient = $quest !== null && (int) $dispute->opened_by_user_id === (int) $quest->client_id;

        return [
            'dispute' => $this->row($dispute, true),
            'parties' => [
                'client' => $quest?->client?->only(['id', 'name', 'email', 'first_name']),
                'freelancer' => $quest?->freelancer?->only(['id', 'name', 'email', 'first_name']),
                'filed_by_party' => $openedByClient ? 'client' : 'freelancer',
            ],
            'intake' => $this->intakeSummary($dispute),
            'timeline' => data_get($dispute->structured_intake, 'timeline', []),
            'messages' => $dispute->messages->map(fn (DisputeMessage $message) => [
                'id' => $message->id,
                'kind' => $message->kind?->value ?? (string) $message->kind,
                'body' => $message->body,
                'author' => $message->user?->name ?? 'System',
                'created_at' => $message->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])->values()->all(),
            'events' => $dispute->events->map(fn (DisputeEvent $event) => [
                'id' => $event->id,
                'action' => $event->action,
                'action_label' => app(DisputeEventLabelService::class)->label($event->action),
                'properties' => $event->properties ?? [],
                'actor' => $event->actor?->name ?? 'System',
                'created_at' => $event->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])->values()->all(),
            'internal_notes' => $dispute->events
                ->where('action', 'staff_internal_note')
                ->values()
                ->map(fn (DisputeEvent $event) => [
                    'body' => data_get($event->properties, 'body'),
                    'actor' => $event->actor?->name,
                    'created_at' => $event->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ])->all(),
            'assessments' => $dispute->assessments->map(fn (DisputeAssessment $assessment) => $this->assessmentPayload($assessment))->values()->all(),
            'current_assessment' => $this->assessmentPayload(
                $dispute->assessments->firstWhere('staff_user_id', $viewer->id)
                    ?? $dispute->assessments->first()
            ),
            'latest_submitted_assessment' => $dispute->latestSubmittedAssessment()
                ? $this->assessmentPayload($dispute->latestSubmittedAssessment())
                : null,
            'permissions' => $permissions->permissionsFor($viewer, $dispute),
            'investigation_checklist_options' => $this->investigationChecklistOptions(),
            'recommendation_options' => collect(\App\Enums\DisputeAssessmentRecommendation::cases())
                ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])
                ->values()
                ->all(),
            'workflow' => $this->workflowChecklist->payload($dispute),
            'staff_guidance' => $this->staffGuidance($dispute),
            'evidence_review' => data_get($dispute->workflow_state, 'evidence_reviewed', []),
            'evidence_templates' => config('disputes.management.evidence_request_templates', []),
            'sanction_options' => config('disputes.management.sanction_options', []),
            'outcome_action_options' => config('disputes.management.outcome_action_options', []),
            'resolution_options' => app(DisputeResolutionMatrixService::class)->optionsForActor(
                $permissions->isSuperAdmin($viewer) ? 'super_admin' : 'staff'
            ),
            'resolution_requests' => $dispute->resolutionRequests()
                ->with('requestedBy:id,name,first_name')
                ->latest('id')
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'option' => $row->option,
                    'option_label' => $row->optionEnum()?->label() ?? $row->option,
                    'party_role' => $row->party_role,
                    'terms' => $row->terms ?? [],
                    'status' => $row->status,
                    'requested_by' => $row->requestedBy?->name,
                    'created_at' => $row->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ])
                ->values()
                ->all(),
            'mediation_sessions' => $dispute->mediationSessions->map(fn ($session) => [
                'id' => $session->id,
                'status' => $session->status,
                'scheduled_at' => $session->scheduled_at?->timezone('Africa/Lagos')->toIso8601String(),
                'meeting_url' => $session->meeting_url,
                'instructions' => $session->instructions,
                'completed_at' => $session->completed_at?->timezone('Africa/Lagos')->toIso8601String(),
                'opened_by' => $session->openedBy?->name,
            ])->values()->all(),
            'precedents' => $dispute->precedents->map(fn ($precedent) => [
                'id' => $precedent->id,
                'title' => $precedent->title,
                'summary' => $precedent->summary,
                'category' => $precedent->category,
                'created_by' => $precedent->createdBy?->name,
                'created_at' => $precedent->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])->values()->all(),
            'report_download_url' => $dispute->report_path
                ? route('admin.api.disputes.download_report', $dispute)
                : null,
            'party_messages' => $dispute->messages
                ->filter(fn (DisputeMessage $m) => $m->kind !== DisputeMessageKind::System || in_array($m->structured_key, ['staff_contact', 'staff_notice', 'staff_evidence_request'], true))
                ->map(fn (DisputeMessage $message) => [
                    'id' => $message->id,
                    'kind' => $message->kind?->value ?? (string) $message->kind,
                    'body' => $message->body,
                    'author' => $message->user?->name ?? 'System',
                    'structured_key' => $message->structured_key,
                    'created_at' => $message->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ])->values()->all(),
            'self_resolution_activity' => $this->selfResolutionActivity($dispute),
            'negotiation_history' => $dispute->negotiationOffers()
                ->with('offeredBy:id,name,first_name')
                ->orderBy('id')
                ->get()
                ->map(fn ($offer) => [
                    'id' => $offer->id,
                    'party_role' => $offer->party_role,
                    'offered_by' => $offer->offeredBy?->name,
                    'attempt_number' => $offer->attempt_number,
                    'option' => $offer->option,
                    'option_label' => $offer->optionEnum()?->label() ?? $offer->option,
                    'summary' => $offer->summaryLabel(),
                    'terms' => $offer->terms ?? [],
                    'status' => $offer->status?->value ?? (string) $offer->status,
                    'is_final_offer' => $offer->is_final_offer,
                    'created_at' => $offer->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ])
                ->values()
                ->all(),
            'negotiation_meta' => [
                'phase' => $dispute->negotiation_phase,
                'binding_mediation_ack_client' => $dispute->binding_mediation_ack_client_at?->toIso8601String(),
                'binding_mediation_ack_freelancer' => $dispute->binding_mediation_ack_freelancer_at?->toIso8601String(),
                'rejection_window_ends_at' => $dispute->rejection_window_ends_at?->timezone('Africa/Lagos')->toIso8601String(),
                'enforcement_pending_at' => $dispute->enforcement_pending_at?->timezone('Africa/Lagos')->toIso8601String(),
            ],
            'appeals' => $dispute->appeals()
                ->with('filedBy:id,name,first_name')
                ->latest('id')
                ->get()
                ->map(fn ($appeal) => [
                    'id' => $appeal->id,
                    'status' => $appeal->status,
                    'party_role' => $appeal->party_role,
                    'filed_by' => $appeal->filedBy?->name,
                    'unfair_reason' => $appeal->unfair_reason,
                    'proposed_option' => $appeal->proposed_option,
                    'proposed_terms' => $appeal->proposed_terms ?? [],
                    'counter_response' => $appeal->counter_response,
                    'upheld_original' => $appeal->upheld_original,
                    'review_outcome_notes' => $appeal->review_outcome_notes,
                    'created_at' => $appeal->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ])
                ->values()
                ->all(),
            'contract_disputes' => $dispute->contract
                ? $this->contractDisputeHistory($dispute->contract)
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function selfResolutionActivity(QuestDispute $dispute): array
    {
        $events = $dispute->events
            ->whereIn('action', self::SELF_RESOLUTION_EVENT_ACTIONS)
            ->values()
            ->map(fn (DisputeEvent $event) => [
                'id' => $event->id,
                'action' => $event->action,
                'action_label' => app(DisputeEventLabelService::class)->label($event->action),
                'properties' => $event->properties ?? [],
                'actor' => $event->actor?->name ?? 'System',
                'created_at' => $event->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])
            ->all();

        return [
            'resolved_by_parties' => $this->isPartySelfResolved($dispute),
            'outcome_label' => app(DisputeResolutionOutcomeLabelService::class)->label($dispute->resolution_outcome),
            'management_resolved_at' => $dispute->management_resolved_at?->timezone('Africa/Lagos')->toIso8601String(),
            'settlement_offer_count' => $dispute->events->where('action', 'dispute.settlement_offered')->count(),
            'resolution_proposal_count' => $dispute->resolutionRequests()->count(),
            'events' => $events,
        ];
    }

    protected function isPartySelfResolved(QuestDispute $dispute): bool
    {
        return in_array((string) $dispute->resolution_outcome, self::PARTY_SELF_OUTCOMES, true);
    }

    protected function needsAdminAcknowledgment(QuestDispute $dispute): bool
    {
        return $dispute->management_status === QuestDisputeManagementStatus::Closed
            && $this->isPartySelfResolved($dispute)
            && $dispute->super_admin_decided_by === null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function assessmentPayload(?DisputeAssessment $assessment): ?array
    {
        if ($assessment === null) {
            return null;
        }

        return [
            'id' => $assessment->id,
            'status' => $assessment->status,
            'quality_rating' => $assessment->quality_rating,
            'investigation_checklist' => $assessment->investigation_checklist ?? [],
            'violation_status' => $assessment->violation_status,
            'key_findings' => $assessment->key_findings ?? [],
            'recommendation' => $assessment->recommendation?->value,
            'recommendation_label' => $assessment->recommendation?->label(),
            'recommended_client_share_percent' => $assessment->recommended_client_share_percent,
            'reasoning' => $assessment->reasoning,
            'time_spent_minutes' => $assessment->time_spent_minutes,
            'recommended_sanction' => $assessment->recommended_sanction,
            'alternate_recommendations' => $assessment->alternate_recommendations ?? [],
            'super_admin_rating' => $assessment->super_admin_rating,
            'super_admin_feedback' => $assessment->super_admin_feedback,
            'staff' => $assessment->staff?->only(['id', 'name', 'email']),
            'submitted_at' => $assessment->submitted_at?->timezone('Africa/Lagos')->toIso8601String(),
            'updated_at' => $assessment->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function intakeSummary(QuestDispute $dispute): array
    {
        $intake = $dispute->structured_intake ?? [];

        return [
            'description' => $dispute->opening_summary,
            'category_label' => $intake['category_label'] ?? null,
            'resolution_requested' => $intake['resolution_requested'] ?? null,
            'impact' => $intake['impact'] ?? [],
            'evidence_files' => $intake['evidence_files'] ?? [],
            'external_links' => $intake['external_links'] ?? [],
            'affected_areas' => $intake['affected_areas'] ?? [],
            'preferred_process' => $intake['preferred_process'] ?? null,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function investigationChecklistOptions(): array
    {
        return [
            ['key' => 'reviewed_deliverable', 'label' => __('Reviewed deliverable')],
            ['key' => 'checked_messages', 'label' => __('Checked platform messages')],
            ['key' => 'assessed_spec_compliance', 'label' => __('Assessed spec compliance')],
            ['key' => 'reviewed_revisions', 'label' => __('Reviewed revision history')],
            ['key' => 'reviewed_contract_terms', 'label' => __('Reviewed contract terms')],
            ['key' => 'reviewed_payment_escrow', 'label' => __('Reviewed payment/escrow')],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function staffGuidance(QuestDispute $dispute): ?array
    {
        $pending = data_get($dispute->workflow_state, 'pending_staff_action');
        if (! is_array($pending) || empty($pending['type'])) {
            return null;
        }

        $type = (string) $pending['type'];

        return [
            'type' => $type,
            'type_label' => match ($type) {
                'clarification' => __('Super Admin clarification requested'),
                'more_review' => __('Super Admin requested more review'),
                default => __('Super Admin action required'),
            },
            'note' => $pending['note'] ?? null,
            'requested_at' => $pending['requested_at'] ?? null,
            'requested_by' => $pending['requested_by_name'] ?? null,
            'can_respond' => in_array($type, ['clarification', 'more_review'], true),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function contractPayload(?QuestContract $contract, ?User $viewer = null): ?array
    {
        if ($contract === null) {
            return null;
        }

        $isSuperAdmin = $viewer?->role?->slug === 'super_admin';

        return [
            'reference_code' => $contract->reference_code,
            'url' => $isSuperAdmin
                ? route('admin.contracts.view', $contract->reference_code)
                : route('operations.contract-management.index', ['q' => $contract->reference_code]),
            'party_url' => route('contracts.show', $contract->reference_code),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contractDisputeHistory(QuestContract $contract): array
    {
        return QuestDispute::query()
            ->where('quest_id', $contract->quest_id)
            ->where('quest_offer_id', $contract->quest_offer_id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (QuestDispute $dispute) => [
                'id' => $dispute->id,
                'uuid' => $dispute->uuid,
                'reference' => $dispute->displayReference(),
                'status' => $dispute->status?->value ?? (string) $dispute->status,
                'management_status_label' => $dispute->management_status?->label() ?? (string) $dispute->management_status,
                'is_active' => $dispute->isActiveOnContract(),
                'created_at' => $dispute->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'resolved_at' => $dispute->resolved_at?->timezone('Africa/Lagos')->toIso8601String(),
                'admin_url' => route('admin.disputes.index', ['q' => $dispute->uuid]),
                'party_url' => route('disputes.show', $dispute),
            ])
            ->values()
            ->all();
    }
}
