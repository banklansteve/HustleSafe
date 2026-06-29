<?php

namespace App\Services\Disputes;

use App\Enums\QuestDisputeManagementStatus;
use App\Models\QuestDispute;
use App\Models\User;

class DisputeManagementPermissionService
{
    public function isSuperAdmin(?User $user): bool
    {
        return $user?->role?->slug === 'super_admin';
    }

    public function isStaffAdmin(?User $user): bool
    {
        return $user?->role?->slug === 'admin';
    }

    public function canViewDispute(User $user, QuestDispute $dispute): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (! $this->isStaffAdmin($user)) {
            return false;
        }

        return (int) $dispute->assigned_staff_id === (int) $user->id;
    }

    public function assertCanView(User $user, QuestDispute $dispute): void
    {
        if (! $this->canViewDispute($user, $dispute)) {
            abort(403, __('You can only access disputes assigned to you.'));
        }
    }

    /**
     * @return array<string, bool>
     */
    public function staffPermissions(User $user, QuestDispute $dispute): array
    {
        $assigned = (int) $dispute->assigned_staff_id === (int) $user->id;
        $active = $dispute->management_status?->isStaffActive() ?? true;

        return [
            'can_view' => $assigned,
            'can_assess' => $assigned && $active,
            'can_request_evidence' => $assigned && $active,
            'can_message_parties' => $assigned,
            'can_mark_ready' => $assigned && in_array($dispute->management_status, [
                QuestDisputeManagementStatus::Open,
                QuestDisputeManagementStatus::PendingResponse,
                QuestDisputeManagementStatus::UnderReview,
            ], true),
            'can_reassign' => false,
            'can_decide' => false,
            'can_execute' => false,
            'can_override' => false,
            'can_create_appeal' => false,
            'can_finalize' => false,
            'can_acknowledge_party_resolution' => false,
            'can_view_all_assessments' => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function superAdminPermissions(User $user, QuestDispute $dispute): array
    {
        $maxReassignments = (int) config('disputes.management.max_reassignments', 2);
        $canReassign = (int) $dispute->reassignment_count < $maxReassignments
            && ! in_array($dispute->management_status, [
                QuestDisputeManagementStatus::Finalized,
            ], true);

        $canDecide = in_array($dispute->management_status, [
            QuestDisputeManagementStatus::ReadyForDecision,
            QuestDisputeManagementStatus::Resolved,
            QuestDisputeManagementStatus::Closed,
        ], true);

        $canAppeal = $dispute->management_status === QuestDisputeManagementStatus::Closed
            && (int) $dispute->super_admin_decided_by === (int) $user->id;

        $canAcknowledgePartyResolution = $dispute->management_status === QuestDisputeManagementStatus::Closed
            && in_array((string) $dispute->resolution_outcome, ['settlement_accepted', 'mutual_resolve'], true)
            && $dispute->super_admin_decided_by === null;

        return [
            'can_view' => true,
            'can_assess' => true,
            'can_request_evidence' => true,
            'can_message_parties' => true,
            'can_mark_ready' => false,
            'can_reassign' => $canReassign,
            'can_decide' => $canDecide,
            'can_execute' => $canDecide,
            'can_override' => $canDecide,
            'can_create_appeal' => $canAppeal,
            'can_finalize' => in_array($dispute->management_status, [
                QuestDisputeManagementStatus::Resolved,
                QuestDisputeManagementStatus::Closed,
            ], true),
            'can_acknowledge_party_resolution' => $canAcknowledgePartyResolution,
            'can_view_all_assessments' => true,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function permissionsFor(User $user, QuestDispute $dispute): array
    {
        if ($this->isSuperAdmin($user)) {
            return $this->superAdminPermissions($user, $dispute);
        }

        return $this->staffPermissions($user, $dispute);
    }
}
