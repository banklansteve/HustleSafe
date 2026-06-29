<?php

namespace App\Services\Operations;

use App\Enums\DisputeAssessmentRecommendation;
use App\Enums\QuestDisputeManagementStatus;
use App\Enums\QuestDisputeStatus;
use App\Models\DisputeAssessment;
use App\Models\DisputeEvent;
use App\Models\QuestDispute;
use App\Models\User;
use App\Services\AdminActivityLogger;
use App\Services\Disputes\DisputeManagementPermissionService;
use App\Services\Disputes\DisputeManagementPresenter;
use App\Services\Disputes\DisputeEnforcementService;
use App\Services\Disputes\DisputeNegotiationService;
use App\Services\Disputes\DisputePartyNotifier;
use App\Services\Disputes\DisputeSmsNotifier;
use App\Services\Disputes\DisputeSuperAdminAlertService;
use App\Services\Disputes\DisputeWorkflowChecklistService;
use App\Enums\DisputeMessageKind;
use App\Models\DisputeMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffDisputeManagementService
{
    public function __construct(
        private readonly AdminActivityLogger $logger,
        private readonly DisputeManagementPermissionService $permissions,
        private readonly DisputeManagementPresenter $presenter,
        private readonly DisputePartyNotifier $partyNotifier,
        private readonly DisputeWorkflowChecklistService $workflowChecklist,
        private readonly DisputeSuperAdminAlertService $superAdminAlerts,
        private readonly DisputeSmsNotifier $smsNotifier,
        private readonly DisputeEnforcementService $enforcement,
    ) {}

    public function listing(Request $request, bool $staffScoped = true): LengthAwarePaginator
    {
        $filter = (string) $request->input('filter', 'all');
        $sort = (string) $request->input('sort', 'newest');
        $q = trim((string) $request->input('q', ''));

        $query = QuestDispute::query()
            ->with(['quest:id,title,reference_code', 'openedBy:id,name,email', 'assignedStaff:id,name,email']);

        if ($staffScoped) {
            $query->where('assigned_staff_id', $request->user()->id);
        }

        $query = match ($filter) {
            'open' => $query->where('management_status', QuestDisputeManagementStatus::Open),
            'under_review' => $query->where('management_status', QuestDisputeManagementStatus::UnderReview),
            'pending_response' => $query->where('management_status', QuestDisputeManagementStatus::PendingResponse),
            'needs_action' => $query->whereNotNull('workflow_state->pending_staff_action'),
            'ready_for_decision' => $query->where('management_status', QuestDisputeManagementStatus::ReadyForDecision),
            'awaiting_mutual' => $query->where('management_status', QuestDisputeManagementStatus::AwaitingMutualApproval),
            'mediation' => $query->where('management_status', QuestDisputeManagementStatus::Mediation),
            'resolved' => $query->whereIn('management_status', [
                QuestDisputeManagementStatus::Resolved,
                QuestDisputeManagementStatus::Closed,
                QuestDisputeManagementStatus::Finalized,
            ]),
            'party_resolved' => $query
                ->whereIn('resolution_outcome', ['settlement_accepted', 'mutual_resolve'])
                ->whereNull('super_admin_decided_by'),
            default => $query->whereNotIn('management_status', [QuestDisputeManagementStatus::Finalized]),
        };

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('uuid', 'like', "%{$q}%")
                    ->orWhereHas('quest', fn ($quest) => $quest->where('title', 'like', "%{$q}%")->orWhere('reference_code', 'like', "%{$q}%"))
                    ->orWhereHas('openedBy', fn ($user) => $user->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
            });
        }

        $query = match ($sort) {
            'oldest' => $query->oldest('id'),
            'high_value' => $query->orderByDesc('disputed_amount_minor'),
            'overdue' => $query->orderBy('ready_for_decision_at')->orderBy('created_at'),
            default => $query->latest('id'),
        };

        return $query->paginate(min(100, max(25, $request->integer('per_page', 50))))
            ->withQueryString()
            ->through(fn (QuestDispute $dispute) => $this->presenter->row($dispute, false, $request->user()));
    }

    /**
     * @return array{assigned_total: int, pending_action: int, filters: list<array<string, string>>, sorts: list<array<string, string>>}
     */
    public function queueSummary(User $staff): array
    {
        $assignedTotal = QuestDispute::query()
            ->where('assigned_staff_id', $staff->id)
            ->whereNotIn('management_status', [QuestDisputeManagementStatus::Finalized])
            ->count();

        $pendingAction = QuestDispute::query()
            ->where('assigned_staff_id', $staff->id)
            ->whereIn('management_status', [
                QuestDisputeManagementStatus::Open,
                QuestDisputeManagementStatus::UnderReview,
                QuestDisputeManagementStatus::PendingResponse,
            ])
            ->count();

        $needsSuperAdminResponse = QuestDispute::query()
            ->where('assigned_staff_id', $staff->id)
            ->whereNotNull('workflow_state->pending_staff_action')
            ->count();

        return [
            'assigned_total' => $assignedTotal,
            'pending_action' => $pendingAction,
            'needs_super_admin_response' => $needsSuperAdminResponse,
            'filters' => [
                ['key' => 'all', 'label' => __('All')],
                ['key' => 'open', 'label' => __('Open')],
                ['key' => 'under_review', 'label' => __('Under review')],
                ['key' => 'needs_action', 'label' => __('Super Admin requests')],
                ['key' => 'pending_response', 'label' => __('Pending response')],
                ['key' => 'awaiting_mutual', 'label' => __('Mutual agreement')],
                ['key' => 'mediation', 'label' => __('Mediation')],
                ['key' => 'party_resolved', 'label' => __('Party self-resolved')],
                ['key' => 'resolved', 'label' => __('Resolved')],
            ],
            'sorts' => [
                ['key' => 'newest', 'label' => __('Newest')],
                ['key' => 'oldest', 'label' => __('Oldest')],
                ['key' => 'high_value', 'label' => __('High value')],
                ['key' => 'overdue', 'label' => __('Overdue')],
            ],
        ];
    }

    public function detail(QuestDispute $dispute, User $viewer): array
    {
        $this->permissions->assertCanView($viewer, $dispute);

        return $this->presenter->detail($dispute, $viewer, $this->permissions);
    }

    public function claim(QuestDispute $dispute, User $staff, Request $request): QuestDispute
    {
        if ($dispute->assigned_staff_id && (int) $dispute->assigned_staff_id !== (int) $staff->id) {
            throw ValidationException::withMessages(['claim' => __('This dispute is already assigned to another staff member.')]);
        }

        $dispute->forceFill([
            'assigned_staff_id' => $staff->id,
            'staff_claimed_at' => now(),
            'management_status' => $dispute->management_status ?? QuestDisputeManagementStatus::Open,
        ])->save();

        $this->recordEvent($dispute, $staff, 'staff_claimed', ['staff_id' => $staff->id]);
        $this->logger->log($staff, 'operations.dispute.claimed', QuestDispute::class, $dispute->id, [], $request);

        return $dispute->fresh();
    }

    public function acknowledge(QuestDispute $dispute, User $staff, Request $request): QuestDispute
    {
        $this->permissions->assertCanView($staff, $dispute);

        if ($dispute->staff_acknowledged_at !== null) {
            return $dispute;
        }

        $dispute->forceFill([
            'staff_acknowledged_at' => now(),
            'management_status' => $dispute->management_status === QuestDisputeManagementStatus::Open
                ? QuestDisputeManagementStatus::UnderReview
                : $dispute->management_status,
        ])->save();

        $this->workflowChecklist->autoComplete($dispute, 'review_claim');

        $dispute->loadMissing('quest.client', 'quest.freelancer');
        $questTitle = $dispute->quest?->title ?? __('your contract');
        $parties = array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]);

        $this->partyNotifier->notifyMany(
            $parties,
            $dispute,
            __('Your dispute is under review'),
            __('HustleSafe staff has started reviewing the dispute on “:title”.', ['title' => $questTitle]),
            __('We will contact you if we need more information. You can still view the dispute file and add updates.'),
            __('View dispute'),
            'both',
        );

        $this->recordEvent($dispute, $staff, 'staff_acknowledged', []);
        $this->logger->log($staff, 'operations.dispute.acknowledged', QuestDispute::class, $dispute->id, [], $request);

        return $dispute->fresh();
    }

    public function updateChecklist(QuestDispute $dispute, User $staff, array $data, Request $request): array
    {
        $this->permissions->assertCanView($staff, $dispute);
        $completed = array_values(array_unique(array_filter((array) ($data['completed'] ?? []))));
        $this->workflowChecklist->saveCompleted($dispute, $completed);
        $this->recordEvent($dispute, $staff, 'staff_checklist_updated', ['completed' => $completed]);
        $this->logger->log($staff, 'operations.dispute.checklist', QuestDispute::class, $dispute->id, $data, $request);

        return $this->workflowChecklist->payload($dispute->fresh());
    }

    public function markEvidenceReviewed(QuestDispute $dispute, User $staff, array $data, Request $request): QuestDispute
    {
        $this->permissions->assertCanView($staff, $dispute);
        $key = (string) ($data['key'] ?? '');
        $reviewed = (array) data_get($dispute->workflow_state, 'evidence_reviewed', []);
        $reviewed[$key] = [
            'reviewed_at' => now()->toIso8601String(),
            'note' => $data['note'] ?? null,
            'status' => $data['status'] ?? 'reviewed',
        ];

        $this->workflowChecklist->patchState($dispute, ['evidence_reviewed' => $reviewed]);
        $this->workflowChecklist->autoComplete($dispute->fresh(), 'review_evidence');
        $this->recordEvent($dispute, $staff, 'staff_evidence_reviewed', ['key' => $key, 'note' => $data['note'] ?? null]);

        return $dispute->fresh();
    }

    public function markAwaitingInfo(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        $this->requestEvidence($dispute, $staff, [
            'body' => $data['body'] ?? __('Please provide the requested information to continue our investigation.'),
            'audience' => $data['audience'] ?? 'both',
        ], $request);

        $this->recordEvent($dispute, $staff, 'staff_awaiting_info', ['audience' => $data['audience'] ?? 'both']);
    }

    public function requestReassignment(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        $this->permissions->assertCanView($staff, $dispute);
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('Provide a reason for reassignment.')]);
        }

        $this->recordEvent($dispute, $staff, 'staff_reassign_requested', ['reason' => $reason]);
        $this->superAdminAlerts->notifyReassignmentRequested($dispute, $staff, $reason);
        $this->logger->log($staff, 'operations.dispute.reassign_request', QuestDispute::class, $dispute->id, $data, $request);
    }

    public function internalNote(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        $this->permissions->assertCanView($staff, $dispute);
        $this->recordEvent($dispute, $staff, 'staff_internal_note', ['body' => $data['body']]);
        $this->logger->log($staff, 'operations.dispute.internal_note', QuestDispute::class, $dispute->id, [], $request);
    }

    public function postNotice(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        $this->permissions->assertCanView($staff, $dispute);

        DisputeMessage::query()->create([
            'quest_dispute_id' => $dispute->id,
            'user_id' => $staff->id,
            'kind' => DisputeMessageKind::System,
            'body' => $data['body'],
            'structured_key' => 'staff_notice',
            'structured_payload' => ['audience' => $data['audience'] ?? 'both'],
        ]);

        $dispute->loadMissing('quest.client', 'quest.freelancer');
        $subject = (string) ($data['subject'] ?? __('Dispute update'));
        $questTitle = $dispute->quest?->title ?? __('your contract');

        $this->partyNotifier->notifyMany(
            $this->noticeRecipients($dispute, $data['audience'] ?? 'both'),
            $dispute,
            $subject,
            __('HustleSafe staff posted an update on the dispute for “:title”.', ['title' => $questTitle]),
            $data['body'],
            __('Read update & respond'),
            'both',
        );

        $this->recordEvent($dispute, $staff, 'staff_notice', ['audience' => $data['audience'] ?? 'both']);
        $this->logger->log($staff, 'operations.dispute.notice', QuestDispute::class, $dispute->id, $data, $request);
    }

    public function contactParty(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        $this->permissions->assertCanView($staff, $dispute);
        $dispute->loadMissing('quest.client', 'quest.freelancer');
        $recipient = match ($data['party']) {
            'client' => $dispute->quest?->client,
            'freelancer' => $dispute->quest?->freelancer,
            default => null,
        };

        if ($recipient === null) {
            throw ValidationException::withMessages(['party' => __('Party not found on this dispute.')]);
        }

        DisputeMessage::query()->create([
            'quest_dispute_id' => $dispute->id,
            'user_id' => $staff->id,
            'kind' => DisputeMessageKind::System,
            'body' => $data['body'],
            'structured_key' => 'staff_contact',
            'structured_payload' => ['party' => $data['party']],
        ]);

        $channel = (string) ($data['channel'] ?? 'both');
        $delivery = match ($channel) {
            'in_app' => 'database',
            'email' => 'mail',
            default => 'both',
        };
        $questTitle = $dispute->quest?->title ?? __('your contract');

        $this->partyNotifier->notify(
            $recipient,
            $dispute,
            $data['subject'],
            __('You have a message from HustleSafe dispute staff about “:title”.', ['title' => $questTitle]),
            $data['body'],
            __('Open dispute & reply'),
            $delivery,
        );

        $this->recordEvent($dispute, $staff, 'staff_contact', ['party' => $data['party'], 'channel' => $data['channel'] ?? 'both']);
        $this->logger->log($staff, 'operations.dispute.contact', QuestDispute::class, $dispute->id, $data, $request);
    }

    public function requestEvidence(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        $this->permissions->assertCanView($staff, $dispute);

        DisputeMessage::query()->create([
            'quest_dispute_id' => $dispute->id,
            'user_id' => $staff->id,
            'kind' => DisputeMessageKind::System,
            'body' => $data['body'],
            'structured_key' => 'staff_evidence_request',
            'structured_payload' => ['audience' => $data['audience'] ?? 'both'],
        ]);

        $dispute->loadMissing('quest.client', 'quest.freelancer');
        $questTitle = $dispute->quest?->title ?? __('your contract');
        $responseHours = (int) config('disputes.self_resolution_response_hours', 48);

        $this->partyNotifier->notifyMany(
            $this->noticeRecipients($dispute, $data['audience'] ?? 'both'),
            $dispute,
            __('Additional evidence requested'),
            __('Staff needs more information to investigate the dispute on “:title”. Please respond within :hours hours.', [
                'title' => $questTitle,
                'hours' => $responseHours,
            ]),
            $data['body'],
            __('Upload evidence & respond'),
            'both',
        );

        foreach ($this->noticeRecipients($dispute, $data['audience'] ?? 'both') as $recipient) {
            $this->smsNotifier->notifyEvidenceDeadline($recipient, $dispute, $responseHours);
        }

        $dispute->forceFill(['management_status' => QuestDisputeManagementStatus::PendingResponse])->save();
        $this->recordEvent($dispute, $staff, 'staff_evidence_request', ['body' => $data['body']]);
        $this->logger->log($staff, 'operations.dispute.evidence_request', QuestDispute::class, $dispute->id, $data, $request);
    }

    public function saveAssessment(QuestDispute $dispute, User $staff, array $data, Request $request): DisputeAssessment
    {
        $this->permissions->assertCanView($staff, $dispute);
        $perms = $this->permissions->staffPermissions($staff, $dispute);
        if (! $perms['can_assess']) {
            throw ValidationException::withMessages(['assessment' => __('You cannot edit the assessment for this dispute.')]);
        }

        $submit = (bool) ($data['submit'] ?? false);

        return DB::transaction(function () use ($dispute, $staff, $data, $request, $submit): DisputeAssessment {
            $assessment = DisputeAssessment::query()->firstOrNew([
                'quest_dispute_id' => $dispute->id,
                'staff_user_id' => $staff->id,
            ]);

            $assessment->fill([
                'quality_rating' => $data['quality_rating'] ?? null,
                'investigation_checklist' => $data['investigation_checklist'] ?? [],
                'violation_status' => $data['violation_status'] ?? null,
                'key_findings' => $data['key_findings'] ?? [],
                'recommendation' => isset($data['recommendation']) ? DisputeAssessmentRecommendation::from($data['recommendation']) : null,
                'recommended_client_share_percent' => $data['recommended_client_share_percent'] ?? null,
                'recommended_sanction' => $data['recommended_sanction'] ?? null,
                'alternate_recommendations' => $data['alternate_recommendations'] ?? [],
                'reasoning' => $data['reasoning'] ?? null,
                'time_spent_minutes' => $data['time_spent_minutes'] ?? null,
                'status' => $submit ? 'submitted' : 'draft',
                'submitted_at' => $submit ? now() : $assessment->submitted_at,
            ])->save();

            if ($dispute->management_status === QuestDisputeManagementStatus::Open) {
                $dispute->update(['management_status' => QuestDisputeManagementStatus::UnderReview]);
            }

            $this->recordEvent($dispute, $staff, $submit ? 'staff_assessment_submitted' : 'staff_assessment_saved', [
                'assessment_id' => $assessment->id,
            ]);
            $this->logger->log($staff, 'operations.dispute.assessment', QuestDispute::class, $dispute->id, $data, $request);

            if ($submit) {
                $this->workflowChecklist->autoComplete($dispute, 'write_assessment');
                $this->workflowChecklist->autoComplete($dispute, 'rate_quality');
                $this->workflowChecklist->autoComplete($dispute, 'make_finding');
                $this->superAdminAlerts->notifyAssessmentSubmitted($dispute->fresh(), $staff);
            }

            return $assessment->fresh();
        });
    }

    public function respondToStaffGuidance(QuestDispute $dispute, User $staff, array $data, Request $request): QuestDispute
    {
        $this->permissions->assertCanView($staff, $dispute);

        $pending = data_get($dispute->workflow_state, 'pending_staff_action');
        if (! is_array($pending) || empty($pending['type'])) {
            throw ValidationException::withMessages(['guidance' => __('There is no pending Super Admin request on this dispute.')]);
        }

        $type = (string) $pending['type'];
        $action = $type === 'clarification' ? 'staff.clarification_response' : 'staff.more_review_response';

        $this->recordEvent($dispute, $staff, $action, [
            'body' => $data['body'],
            'guidance_type' => $type,
            'in_response_to' => $pending['note'] ?? null,
        ]);

        $state = $dispute->workflow_state ?? [];
        unset($state['pending_staff_action']);
        $dispute->forceFill(['workflow_state' => $state])->save();

        $this->superAdminAlerts->notifyStaffGuidanceResponse($dispute->fresh(), $staff, $type, $data['body']);
        $this->logger->log($staff, 'operations.dispute.guidance_response', QuestDispute::class, $dispute->id, $data, $request);

        return $dispute->fresh();
    }

    public function markReadyForDecision(QuestDispute $dispute, User $staff, Request $request): QuestDispute
    {
        $this->permissions->assertCanView($staff, $dispute);
        $perms = $this->permissions->staffPermissions($staff, $dispute);
        if (! $perms['can_mark_ready']) {
            throw ValidationException::withMessages(['dispute' => __('This dispute cannot be marked ready yet.')]);
        }

        if (! $dispute->staff_acknowledged_at) {
            throw ValidationException::withMessages(['acknowledge' => __('Acknowledge the dispute before sending to Super Admin.')]);
        }

        $workflow = $this->workflowChecklist->payload($dispute);
        if ($workflow['required_completed'] < $workflow['required_total'] - 1) {
            throw ValidationException::withMessages(['checklist' => __('Complete the investigation checklist before escalating.')]);
        }

        $submitted = $dispute->assessments()->where('staff_user_id', $staff->id)->where('status', 'submitted')->exists();
        if (! $submitted) {
            throw ValidationException::withMessages(['assessment' => __('Submit your assessment before sending to Super Admin.')]);
        }

        if ($dispute->negotiation_phase === \App\Enums\DisputeNegotiationPhase::Mediation->value) {
            if ($dispute->binding_mediation_ack_client_at === null || $dispute->binding_mediation_ack_freelancer_at === null) {
                throw ValidationException::withMessages(['binding' => __('Both parties must acknowledge binding mediation before Super Admin review.')]);
            }
        }

        $dispute->forceFill([
            'management_status' => QuestDisputeManagementStatus::ReadyForDecision,
            'ready_for_decision_at' => now(),
            'status' => QuestDisputeStatus::AwaitingRuling,
        ])->save();

        $state = $dispute->workflow_state ?? [];
        unset($state['pending_staff_action']);
        if ($state !== ($dispute->workflow_state ?? [])) {
            $dispute->forceFill(['workflow_state' => $state])->save();
        }

        $this->recordEvent($dispute, $staff, 'management.ready_for_decision', []);
        $this->logger->log($staff, 'operations.dispute.ready_for_decision', QuestDispute::class, $dispute->id, [], $request);

        $this->workflowChecklist->autoComplete($dispute, 'submit_for_super_admin');
        $this->superAdminAlerts->notifyReadyForDecision($dispute->fresh(), $staff);

        $dispute->loadMissing('quest');
        $questTitle = $dispute->quest?->title ?? __('your contract');
        $parties = array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]);

        $this->partyNotifier->notifyMany(
            $parties,
            $dispute,
            __('Dispute sent for final review'),
            __('Staff investigation on “:title” is complete. A Super Admin will issue a binding decision soon.', ['title' => $questTitle]),
            __('You do not need to take action unless we contact you again. You can still view the dispute file and audit trail for updates.'),
            __('View dispute status'),
            'both',
        );

        return $dispute->fresh();
    }

    public function approveMutualAgreement(QuestDispute $dispute, User $staff, Request $request): QuestDispute
    {
        $this->permissions->assertCanView($staff, $dispute);

        return $this->enforcement->approveMutualAgreement($dispute, $staff, $request);
    }

    /**
     * @deprecated Staff cannot issue final rulings — use Super Admin decision workflow.
     */
    public function issueRuling(QuestDispute $dispute, User $staff, array $data, Request $request): never
    {
        throw ValidationException::withMessages([
            'ruling' => __('Staff cannot issue final decisions. Mark the case ready for Super Admin review.'),
        ]);
    }

    private function recordEvent(QuestDispute $dispute, User $staff, string $action, array $properties = []): void
    {
        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $staff->id,
            'action' => $action,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<User>
     */
    private function noticeRecipients(QuestDispute $dispute, string $audience): array
    {
        $client = $dispute->quest?->client;
        $freelancer = $dispute->quest?->freelancer;

        return match ($audience) {
            'client' => array_filter([$client]),
            'freelancer' => array_filter([$freelancer]),
            default => array_filter([$client, $freelancer]),
        };
    }
}
