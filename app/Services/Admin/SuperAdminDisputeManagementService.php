<?php

namespace App\Services\Admin;

use App\Enums\QuestDisputeManagementStatus;
use App\Enums\QuestDisputeStatus;
use App\Enums\DisputeMessageKind;
use App\Models\DisputeAssessment;
use App\Models\DisputeEvent;
use App\Models\DisputeMessage;
use App\Models\QuestDispute;
use App\Models\User;
use App\Notifications\QuestDisputeUpdatedNotification;
use App\Services\AdminActivityLogger;
use App\Services\Disputes\DisputeAutoAssignmentService;
use App\Services\Disputes\DisputeManagementPermissionService;
use App\Services\Disputes\DisputeManagementPresenter;
use App\Services\Disputes\DisputeEnforcementService;
use App\Services\Disputes\DisputeAppealService;
use App\Services\Disputes\DisputeExtendedOutcomeService;
use App\Services\Disputes\DisputeMediationService;
use App\Services\Disputes\DisputeOutcomeExecutionService;
use App\Services\Disputes\DisputePartyNotifier;
use App\Services\Disputes\DisputeReportService;
use App\Services\Disputes\DisputeSanctionExecutionService;
use App\Services\Disputes\DisputeSmsNotifier;
use App\Services\Disputes\DisputeSpecialCaseService;
use App\Services\Disputes\DisputeStaffAlertService;
use App\Services\Disputes\DisputeWorkflowChecklistService;
use App\Services\Operations\StaffDisputeManagementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuperAdminDisputeManagementService
{
    public function __construct(
        private readonly AdminActivityLogger $logger,
        private readonly DisputeManagementPermissionService $permissions,
        private readonly DisputeManagementPresenter $presenter,
        private readonly DisputeOutcomeExecutionService $outcomeExecution,
        private readonly DisputeAutoAssignmentService $autoAssignment,
        private readonly StaffDisputeManagementService $staffService,
        private readonly DisputePartyNotifier $partyNotifier,
        private readonly DisputeStaffAlertService $staffAlerts,
        private readonly DisputeSanctionExecutionService $sanctionExecution,
        private readonly DisputeMediationService $mediation,
        private readonly DisputeSpecialCaseService $specialCases,
        private readonly DisputeExtendedOutcomeService $extendedOutcomes,
        private readonly DisputeReportService $reports,
        private readonly DisputeSmsNotifier $smsNotifier,
        private readonly DisputeWorkflowChecklistService $workflowChecklist,
        private readonly DisputeEnforcementService $enforcement,
        private readonly DisputeAppealService $appeals,
    ) {}

    public function listing(Request $request): LengthAwarePaginator
    {
        return $this->staffService->listing($request, staffScoped: false);
    }

    /**
     * @return array<string, int|list<array<string, string>>>
     */
    public function summary(): array
    {
        $counts = QuestDispute::query()
            ->selectRaw('management_status, COUNT(*) as total')
            ->groupBy('management_status')
            ->pluck('total', 'management_status');

        return [
            'total' => (int) QuestDispute::query()->count(),
            'open' => (int) ($counts[QuestDisputeManagementStatus::Open->value] ?? 0),
            'under_review' => (int) ($counts[QuestDisputeManagementStatus::UnderReview->value] ?? 0),
            'ready_for_decision' => (int) ($counts[QuestDisputeManagementStatus::ReadyForDecision->value] ?? 0),
            'resolved' => (int) (($counts[QuestDisputeManagementStatus::Resolved->value] ?? 0)
                + ($counts[QuestDisputeManagementStatus::Closed->value] ?? 0)
                + ($counts[QuestDisputeManagementStatus::Finalized->value] ?? 0)),
            'filters' => [
                ['key' => 'all', 'label' => __('All')],
                ['key' => 'open', 'label' => __('Open')],
                ['key' => 'ready_for_decision', 'label' => __('Ready for decision')],
                ['key' => 'party_resolved', 'label' => __('Party self-resolved')],
                ['key' => 'resolved', 'label' => __('Resolved')],
            ],
            'sorts' => [
                ['key' => 'newest', 'label' => __('Newest')],
                ['key' => 'overdue', 'label' => __('Overdue')],
                ['key' => 'high_value', 'label' => __('Highest value')],
                ['key' => 'assigned', 'label' => __('Assigned to')],
            ],
        ];
    }

    public function detail(QuestDispute $dispute, User $viewer): array
    {
        if (! $this->permissions->isSuperAdmin($viewer)) {
            abort(403);
        }

        $payload = $this->presenter->detail($dispute, $viewer, $this->permissions);
        $payload['staff_options'] = $this->autoAssignment->staffOptions();
        $payload['evidence_templates'] = config('disputes.management.evidence_request_templates', []);
        $payload['sanction_options'] = config('disputes.management.sanction_options', []);

        return $payload;
    }

    public function executeDecision(QuestDispute $dispute, User $superAdmin, array $data, Request $request): QuestDispute
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        if ($dispute->management_status !== QuestDisputeManagementStatus::ReadyForDecision
            && $dispute->management_status !== QuestDisputeManagementStatus::Closed) {
            throw ValidationException::withMessages(['dispute' => __('This dispute is not ready for a Super Admin decision.')]);
        }

        return DB::transaction(function () use ($dispute, $superAdmin, $data, $request): QuestDispute {
            $outcomeAction = (string) ($data['outcome_action'] ?? 'standard_payout');

            if ($outcomeAction === 'force_revision') {
                $this->extendedOutcomes->forceRevision($dispute, $superAdmin, $data);
                $this->logger->log($superAdmin, 'admin.dispute.force_revision', QuestDispute::class, $dispute->id, $data, $request);

                return $dispute->fresh();
            }

            if ($outcomeAction === 'extend_deadline') {
                $this->extendedOutcomes->extendDeadline($dispute, $superAdmin, $data);
                $this->logger->log($superAdmin, 'admin.dispute.extend_deadline', QuestDispute::class, $dispute->id, $data, $request);

                return $dispute->fresh();
            }

            if ($outcomeAction === 'terminate_contract') {
                $this->extendedOutcomes->terminateContract($dispute, $superAdmin, $data);
                $sanctions = $data['sanctions'] ?? [];
                $this->sanctionExecution->execute($dispute, $superAdmin, $this->normalizeSanctions($sanctions, $dispute));
                $this->logger->log($superAdmin, 'admin.dispute.terminate', QuestDispute::class, $dispute->id, $data, $request);

                return $dispute->fresh();
            }

            if ($outcomeAction === 'refund_cancel') {
                $clientShare = 100;
                $sanctions = $this->normalizeSanctions($data['sanctions'] ?? [], $dispute);
                $payout = $this->outcomeExecution->execute(
                    $dispute,
                    $superAdmin,
                    $clientShare,
                    $data['decision_notes'] ?? null,
                    $sanctions,
                );
                $this->extendedOutcomes->terminateContract($dispute, $superAdmin, $data);
                $this->sanctionExecution->execute($dispute, $superAdmin, $sanctions);
                $dispute->forceFill([
                    'management_status' => QuestDisputeManagementStatus::Closed,
                    'status' => QuestDisputeStatus::Resolved,
                    'resolved_at' => now(),
                    'management_resolved_at' => now(),
                    'resolution_outcome' => 'refund_cancel',
                    'outcome_action' => 'refund_cancel',
                    'final_client_share_percent' => $clientShare,
                    'super_admin_decided_by' => $superAdmin->id,
                    'super_admin_decided_at' => now(),
                    'super_admin_decision_notes' => $data['decision_notes'] ?? null,
                    'sanction_payload' => $sanctions,
                ])->save();
                $this->recordEvent($dispute, $superAdmin, 'super_admin.decision_executed', [
                    'client_share_percent' => $clientShare,
                    'payout' => $payout,
                    'outcome_action' => 'refund_cancel',
                ]);
                $this->logger->log($superAdmin, 'admin.dispute.refund_cancel', QuestDispute::class, $dispute->id, $data, $request);

                return $dispute->fresh();
            }

            if ($outcomeAction === 'mediation') {
                $this->mediation->schedule($dispute, $superAdmin, $data);
                $this->logger->log($superAdmin, 'admin.dispute.mediation', QuestDispute::class, $dispute->id, $data, $request);

                return $dispute->fresh();
            }

            $clientShare = (int) $data['client_share_percent'];
            $sanctions = $this->normalizeSanctions($data['sanctions'] ?? [], $dispute);

            if ($outcomeAction === 'standard_payout' && $this->enforcement->requiresEnforcementWindow($dispute)) {
                $dispute = $this->enforcement->issuePendingEnforcement(
                    $dispute,
                    $superAdmin,
                    $clientShare,
                    $sanctions,
                    $data['decision_notes'] ?? null,
                    $outcomeAction,
                );
                $this->logger->log($superAdmin, 'admin.dispute.decision_pending', QuestDispute::class, $dispute->id, $data, $request);

                return $dispute->fresh();
            }

            $payout = $this->outcomeExecution->execute(
                $dispute,
                $superAdmin,
                $clientShare,
                $data['decision_notes'] ?? null,
                $sanctions,
            );

            $this->sanctionExecution->execute($dispute, $superAdmin, $sanctions);

            $appealDays = (int) config('disputes.management.appeal_window_days', 7);

            $dispute->forceFill([
                'management_status' => QuestDisputeManagementStatus::Closed,
                'status' => QuestDisputeStatus::Resolved,
                'resolved_at' => now(),
                'management_resolved_at' => now(),
                'resolution_outcome' => $data['outcome'] ?? 'super_admin_decision',
                'outcome_action' => $outcomeAction === 'standard_payout' ? 'standard_payout' : $outcomeAction,
                'final_client_share_percent' => $clientShare,
                'ruling_favoured_user_id' => $data['favoured_user_id'] ?? null,
                'super_admin_decided_by' => $superAdmin->id,
                'super_admin_decided_at' => now(),
                'super_admin_decision_notes' => $data['decision_notes'] ?? null,
                'sanction_payload' => $sanctions,
                'appeal_window_ends_at' => now()->addDays($appealDays),
            ])->save();

            $this->recordEvent($dispute, $superAdmin, 'super_admin.decision_executed', [
                'client_share_percent' => $clientShare,
                'payout' => $payout,
                'sanctions' => $sanctions,
            ]);

            $dispute->loadMissing('quest');
            $parties = array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]);
            foreach ($parties as $party) {
                $party->notify(new QuestDisputeUpdatedNotification(
                    $dispute,
                    __('Dispute decision issued'),
                    __('A final decision was issued on dispute :ref.', ['ref' => $dispute->displayReference()]),
                    null,
                    __('View outcome'),
                    'both',
                ));
                $this->smsNotifier->notifyDecision($party, $dispute);
            }

            $this->logger->log($superAdmin, 'admin.dispute.decision', QuestDispute::class, $dispute->id, $data, $request);

            return $dispute->fresh();
        });
    }

    public function reassign(QuestDispute $dispute, User $superAdmin, array $data, Request $request): QuestDispute
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $max = (int) config('disputes.management.max_reassignments', 2);
        if ((int) $dispute->reassignment_count >= $max) {
            throw ValidationException::withMessages(['reassign' => __('This dispute has reached the maximum number of reassignments.')]);
        }

        $newStaff = User::query()->whereKey($data['staff_id'])->whereHas('role', fn ($q) => $q->where('slug', 'admin'))->first();
        if ($newStaff === null) {
            throw ValidationException::withMessages(['staff_id' => __('Select a valid staff admin.')]);
        }

        $previousStaffId = $dispute->assigned_staff_id;
        $previousHours = $dispute->assessments()->where('staff_user_id', $previousStaffId)->sum('time_spent_minutes');

        $dispute->forceFill([
            'assigned_staff_id' => $newStaff->id,
            'staff_claimed_at' => now(),
            'reassignment_count' => (int) $dispute->reassignment_count + 1,
            'management_status' => QuestDisputeManagementStatus::UnderReview,
            'ready_for_decision_at' => null,
            'status' => QuestDisputeStatus::Escalated,
        ])->save();

        $this->recordEvent($dispute, $superAdmin, 'management.reassigned', [
            'from_staff_id' => $previousStaffId,
            'to_staff_id' => $newStaff->id,
            'reason' => $data['reason'],
            'prior_hours' => $previousHours,
        ]);

        if ($previousStaffId) {
            $previous = User::query()->find($previousStaffId);
            $previous?->notify(new QuestDisputeUpdatedNotification(
                $dispute,
                __('Dispute reassigned'),
                __('Dispute :ref was reassigned to :name for a fresh perspective.', [
                    'ref' => $dispute->displayReference(),
                    'name' => $newStaff->name,
                ]),
            ));
        }

        $newStaff->notify(new QuestDisputeUpdatedNotification(
            $dispute,
            __('Dispute assigned to you'),
            __('You are taking over dispute :ref. Prior investigator logged :hours minutes.', [
                'ref' => $dispute->displayReference(),
                'hours' => round($previousHours / 60, 1),
            ]),
        ));

        $this->logger->log($superAdmin, 'admin.dispute.reassigned', QuestDispute::class, $dispute->id, $data, $request);

        return $dispute->fresh();
    }

    public function requestMoreReview(QuestDispute $dispute, User $superAdmin, array $data, Request $request): QuestDispute
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $dispute->forceFill([
            'management_status' => QuestDisputeManagementStatus::UnderReview,
            'ready_for_decision_at' => null,
        ])->save();

        $this->workflowChecklist->patchState($dispute, [
            'pending_staff_action' => [
                'type' => 'more_review',
                'note' => $data['note'] ?? null,
                'requested_at' => now()->toIso8601String(),
                'requested_by_user_id' => $superAdmin->id,
                'requested_by_name' => $superAdmin->name,
            ],
        ]);

        $this->recordEvent($dispute, $superAdmin, 'super_admin.request_more_review', ['note' => $data['note'] ?? null]);
        $this->logger->log($superAdmin, 'admin.dispute.request_review', QuestDispute::class, $dispute->id, $data, $request);

        if ($dispute->assigned_staff_id) {
            $staff = User::query()->find($dispute->assigned_staff_id);
            if ($staff) {
                $this->staffAlerts->notifyReturnedForReview($dispute, $staff, $superAdmin, $data['note'] ?? null);
            }
        }

        return $dispute->fresh();
    }

    public function superAdminNote(QuestDispute $dispute, User $superAdmin, array $data, Request $request): void
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $this->recordEvent($dispute, $superAdmin, 'super_admin.private_note', ['body' => $data['body']]);
        $this->logger->log($superAdmin, 'admin.dispute.private_note', QuestDispute::class, $dispute->id, [], $request);
    }

    public function requestStaffClarification(QuestDispute $dispute, User $superAdmin, array $data, Request $request): void
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $dispute->forceFill([
            'management_status' => QuestDisputeManagementStatus::UnderReview,
            'ready_for_decision_at' => null,
        ])->save();

        $this->workflowChecklist->patchState($dispute, [
            'pending_staff_action' => [
                'type' => 'clarification',
                'note' => $data['note'] ?? null,
                'requested_at' => now()->toIso8601String(),
                'requested_by_user_id' => $superAdmin->id,
                'requested_by_name' => $superAdmin->name,
            ],
        ]);

        $this->recordEvent($dispute, $superAdmin, 'super_admin.request_clarification', ['note' => $data['note']]);
        $this->logger->log($superAdmin, 'admin.dispute.request_clarification', QuestDispute::class, $dispute->id, $data, $request);

        if ($dispute->assigned_staff_id) {
            $staff = User::query()->find($dispute->assigned_staff_id);
            if ($staff) {
                $this->staffAlerts->notifyClarificationRequested($dispute, $staff, $superAdmin, $data['note']);
            }
        }
    }

    public function requestEvidence(QuestDispute $dispute, User $superAdmin, array $data, Request $request): void
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $this->staffService->requestEvidence($dispute, $superAdmin, $data, $request);
        $this->recordEvent($dispute, $superAdmin, 'super_admin.evidence_request', ['audience' => $data['audience'] ?? 'both']);
    }

    public function messageParty(QuestDispute $dispute, User $superAdmin, array $data, Request $request): void
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $this->staffService->contactParty($dispute, $superAdmin, $data, $request);
        $this->recordEvent($dispute, $superAdmin, 'super_admin.direct_message', ['party' => $data['party']]);
    }

    public function holdDispute(QuestDispute $dispute, User $superAdmin, array $data, Request $request): QuestDispute
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $dispute->forceFill([
            'held_at' => now(),
            'hold_reason' => $data['reason'],
        ])->save();

        $this->recordEvent($dispute, $superAdmin, 'super_admin.hold', ['reason' => $data['reason']]);
        $this->logger->log($superAdmin, 'admin.dispute.hold', QuestDispute::class, $dispute->id, $data, $request);

        return $dispute->fresh();
    }

    public function releaseHold(QuestDispute $dispute, User $superAdmin, Request $request): QuestDispute
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $dispute->forceFill(['held_at' => null, 'hold_reason' => null])->save();
        $this->recordEvent($dispute, $superAdmin, 'super_admin.hold_released', []);
        $this->logger->log($superAdmin, 'admin.dispute.hold_released', QuestDispute::class, $dispute->id, [], $request);

        return $dispute->fresh();
    }

    public function rateStaffAssessment(QuestDispute $dispute, User $superAdmin, array $data, Request $request): void
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $assessment = DisputeAssessment::query()
            ->where('quest_dispute_id', $dispute->id)
            ->whereKey($data['assessment_id'])
            ->firstOrFail();

        $assessment->forceFill([
            'super_admin_rating' => $data['rating'],
            'super_admin_feedback' => $data['feedback'] ?? null,
        ])->save();

        $this->recordEvent($dispute, $superAdmin, 'super_admin.rated_assessment', [
            'assessment_id' => $assessment->id,
            'rating' => $data['rating'],
        ]);
        $this->logger->log($superAdmin, 'admin.dispute.rate_assessment', QuestDispute::class, $dispute->id, $data, $request);
    }

    public function approveAssessment(QuestDispute $dispute, User $superAdmin, Request $request): array
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $assessment = $dispute->latestSubmittedAssessment();
        if ($assessment === null) {
            throw ValidationException::withMessages(['assessment' => __('No submitted staff assessment to approve.')]);
        }

        $clientShare = match ($assessment->recommendation?->value) {
            'award_client_full' => 100,
            'award_freelancer_full' => 0,
            'partial_award' => (int) ($assessment->recommended_client_share_percent ?? 50),
            default => 50,
        };

        return [
            'outcome' => $assessment->recommendation?->value ?? 'partial_award',
            'client_share_percent' => $clientShare,
            'decision_notes' => $assessment->reasoning,
            'sanctions' => $this->sanctionsFromAssessment($assessment),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sanctionsFromAssessment(DisputeAssessment $assessment): array
    {
        $sanction = $assessment->recommended_sanction;
        if ($sanction === 'warn_freelancer') {
            return ['warn_freelancer' => true];
        }
        if ($sanction === 'warn_client') {
            return ['warn_client' => true];
        }
        if (in_array($sanction, ['suspend_7', 'suspend_30', 'permanent_ban', 'tier_demotion', 'category_ban'], true)) {
            return ['type' => $sanction];
        }

        return [];
    }

    public function scheduleMediation(QuestDispute $dispute, User $superAdmin, array $data, Request $request)
    {
        $this->assertSuperAdmin($superAdmin);
        $session = $this->mediation->schedule($dispute, $superAdmin, $data);
        $this->logger->log($superAdmin, 'admin.dispute.mediation', QuestDispute::class, $dispute->id, $data, $request);

        return $session;
    }

    public function flagChargebackRisk(QuestDispute $dispute, User $superAdmin, array $data, Request $request): QuestDispute
    {
        $this->assertSuperAdmin($superAdmin);
        $result = $this->specialCases->flagChargebackRisk($dispute, $superAdmin, $data);
        $this->logger->log($superAdmin, 'admin.dispute.chargeback_flag', QuestDispute::class, $dispute->id, $data, $request);

        return $result;
    }

    public function openPatternInvestigation(QuestDispute $dispute, User $superAdmin, array $data, Request $request): QuestDispute
    {
        $this->assertSuperAdmin($superAdmin);
        $result = $this->specialCases->openPatternInvestigation($dispute, $superAdmin, $data);
        $this->logger->log($superAdmin, 'admin.dispute.pattern_investigation', QuestDispute::class, $dispute->id, $data, $request);

        return $result;
    }

    public function createPrecedent(QuestDispute $dispute, User $superAdmin, array $data, Request $request)
    {
        $this->assertSuperAdmin($superAdmin);
        $precedent = $this->specialCases->createPrecedent($dispute, $superAdmin, $data);
        $this->logger->log($superAdmin, 'admin.dispute.precedent', QuestDispute::class, $dispute->id, $data, $request);

        return $precedent;
    }

    public function generateReport(QuestDispute $dispute, User $superAdmin, Request $request): string
    {
        $this->assertSuperAdmin($superAdmin);
        $path = $this->reports->generate($dispute, $superAdmin);
        $this->logger->log($superAdmin, 'admin.dispute.report', QuestDispute::class, $dispute->id, ['path' => $path], $request);

        return $path;
    }

    public function sealAndArchive(QuestDispute $dispute, User $superAdmin, Request $request): QuestDispute
    {
        $this->assertSuperAdmin($superAdmin);
        $result = $this->reports->sealAndArchive($dispute, $superAdmin);
        $this->logger->log($superAdmin, 'admin.dispute.seal', QuestDispute::class, $dispute->id, [], $request);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $sanctions
     * @return array<string, mixed>
     */
    private function normalizeSanctions(array $sanctions, QuestDispute $dispute): array
    {
        $normalized = $sanctions;
        if (! empty($sanctions['recommended_sanction'])) {
            $normalized['type'] = $sanctions['recommended_sanction'];
        }
        if (! empty($sanctions['suspend_user_id']) && empty($normalized['target_user_id'])) {
            $normalized['target_user_id'] = $sanctions['suspend_user_id'];
        }

        return $normalized;
    }

    private function assertSuperAdmin(User $user): void
    {
        if (! $this->permissions->isSuperAdmin($user)) {
            abort(403);
        }
    }

    public function finalize(QuestDispute $dispute, User $superAdmin, Request $request): QuestDispute
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $dispute->forceFill([
            'management_status' => QuestDisputeManagementStatus::Finalized,
            'finalized_at' => now(),
        ])->save();

        $this->recordEvent($dispute, $superAdmin, 'management.finalized', []);
        $this->logger->log($superAdmin, 'admin.dispute.finalized', QuestDispute::class, $dispute->id, [], $request);

        return $dispute->fresh();
    }

    public function acknowledgePartyResolution(QuestDispute $dispute, User $superAdmin, array $data, Request $request): QuestDispute
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        if ($dispute->management_status !== QuestDisputeManagementStatus::Closed) {
            throw ValidationException::withMessages(['dispute' => __('Only party-resolved disputes awaiting review can be acknowledged.')]);
        }

        if (! in_array((string) $dispute->resolution_outcome, ['settlement_accepted', 'mutual_resolve'], true)) {
            throw ValidationException::withMessages(['dispute' => __('This dispute was not resolved by the parties.')]);
        }

        if ($dispute->super_admin_decided_by !== null) {
            throw ValidationException::withMessages(['dispute' => __('This dispute already has a Super Admin decision on record.')]);
        }

        $dispute->forceFill([
            'management_status' => QuestDisputeManagementStatus::Resolved,
        ])->save();

        $this->recordEvent($dispute, $superAdmin, 'management.party_resolution_acknowledged', [
            'resolution_outcome' => $dispute->resolution_outcome,
            'note' => $data['note'] ?? null,
        ]);
        $this->logger->log($superAdmin, 'admin.dispute.party_resolution_acknowledged', QuestDispute::class, $dispute->id, $data, $request);

        if ($dispute->assigned_staff_id) {
            $staff = User::query()->find($dispute->assigned_staff_id);
            if ($staff !== null) {
                $this->staffAlerts->notifyPartyResolutionAcknowledged($dispute, $staff, $superAdmin);
            }
        }

        return $dispute->fresh();
    }

    public function resolveAppeal(QuestDispute $dispute, User $superAdmin, array $data, Request $request): QuestDispute
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        $appeal = $dispute->appeals()
            ->whereIn('status', ['filed', 'counter_pending', 'under_review'])
            ->latest('id')
            ->first();

        if ($appeal === null) {
            throw ValidationException::withMessages(['appeal' => __('No open appeal on this dispute.')]);
        }

        $upheld = (bool) ($data['upheld_original'] ?? true);
        $clientShare = $upheld
            ? (int) ($dispute->final_client_share_percent ?? 50)
            : (int) ($data['client_share_percent'] ?? $dispute->final_client_share_percent ?? 50);

        return DB::transaction(function () use ($dispute, $superAdmin, $data, $request, $appeal, $upheld, $clientShare): QuestDispute {
            $appeal->update([
                'status' => $upheld ? 'upheld' : 'modified',
                'upheld_original' => $upheld,
                'reviewed_by_user_id' => $superAdmin->id,
                'reviewed_at' => now(),
                'review_outcome_notes' => $data['review_outcome_notes'] ?? null,
            ]);

            if (! $upheld) {
                $dispute->forceFill(['final_client_share_percent' => $clientShare])->save();
            }

            if ($dispute->management_status === QuestDisputeManagementStatus::AwaitingEnforcement) {
                $dispute = $this->enforcement->enforcePendingDecision($dispute, $superAdmin);
            } else {
                $dispute->forceFill([
                    'negotiation_phase' => \App\Enums\DisputeNegotiationPhase::Final->value,
                    'management_status' => QuestDisputeManagementStatus::Finalized,
                    'final_binding_at' => now(),
                    'finalized_at' => now(),
                    'appeal_window_ends_at' => null,
                ])->save();
            }

            $this->recordEvent($dispute, $superAdmin, 'super_admin.appeal_resolved', [
                'appeal_id' => $appeal->id,
                'upheld_original' => $upheld,
                'client_share_percent' => $clientShare,
            ]);
            $this->logger->log($superAdmin, 'admin.dispute.appeal_resolved', QuestDispute::class, $dispute->id, $data, $request);

            $dispute->loadMissing(['quest.client', 'quest.freelancer']);
            $outcome = $upheld
                ? __('Appeal denied — original decision is final and binding.')
                : __('Appeal granted — updated decision is final and binding.');
            foreach (array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]) as $party) {
                $party->notify(new QuestDisputeUpdatedNotification(
                    $dispute,
                    __('Appeal resolved'),
                    $outcome,
                    __('No further appeals are allowed on this dispute.'),
                    __('View dispute'),
                    'both',
                ));
            }

            return $dispute->fresh();
        });
    }

    public function createAppealReview(QuestDispute $dispute, User $superAdmin, array $data, Request $request): QuestDispute
    {
        if (! $this->permissions->isSuperAdmin($superAdmin)) {
            abort(403);
        }

        if ((int) $dispute->super_admin_decided_by !== (int) $superAdmin->id) {
            throw ValidationException::withMessages(['appeal' => __('Only the deciding Super Admin can review an appeal on this dispute.')]);
        }

        $dispute->forceFill([
            'management_status' => QuestDisputeManagementStatus::Resolved,
            'appeals_used' => (int) $dispute->appeals_used + 1,
            'appeal_window_ends_at' => null,
        ])->save();

        $this->recordEvent($dispute, $superAdmin, 'super_admin.appeal_review_opened', ['note' => $data['note'] ?? null]);
        $this->logger->log($superAdmin, 'admin.dispute.appeal_review', QuestDispute::class, $dispute->id, $data, $request);

        return $dispute->fresh();
    }

    private function recordEvent(QuestDispute $dispute, User $actor, string $action, array $properties = []): void
    {
        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
