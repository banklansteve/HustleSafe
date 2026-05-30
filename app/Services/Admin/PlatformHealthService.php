<?php

namespace App\Services\Admin;

use App\Enums\StaffRoleGroup;
use App\Enums\UserVerificationStatus;
use App\Models\ConversationMessageFlag;
use App\Models\StaffLeaveRequest;
use App\Models\StaffRoleAssignment;
use App\Models\SupportTicket;
use App\Models\UserVerification;
use App\Support\Hr\StaffRoleGroupLabels;
use Illuminate\Support\Facades\Schema;

class PlatformHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'metrics' => [
                'unresolved_tickets' => $this->unresolvedTicketCount(),
                'flagged_conversations' => $this->flaggedConversationCount(),
                'kyc_queue_depth' => $this->kycQueueDepth(),
            ],
            'staff_availability' => $this->staffAvailabilityByRoleGroup(),
        ];
    }

    public function unresolvedTicketCount(): int
    {
        if (! Schema::hasTable('support_tickets')) {
            return 0;
        }

        return SupportTicket::query()
            ->whereIn('status', ['open', 'in_progress', 'awaiting_customer'])
            ->count();
    }

    public function flaggedConversationCount(): int
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return 0;
        }

        return ConversationMessageFlag::query()
            ->where('status', 'pending')
            ->count();
    }

    public function kycQueueDepth(): int
    {
        if (! Schema::hasTable('user_verifications')) {
            return 0;
        }

        return UserVerification::query()
            ->whereIn('status', [
                UserVerificationStatus::Pending->value,
                UserVerificationStatus::InReview->value,
                UserVerificationStatus::Flagged->value,
            ])
            ->count();
    }

    /**
     * @return list<array{role_group: string, label: string, assigned: int, on_leave: int, available: int, coverage_ok: bool}>
     */
    public function staffAvailabilityByRoleGroup(): array
    {
        if (! Schema::hasTable('staff_role_assignments')) {
            return [];
        }

        $today = now()->toDateString();
        $groups = StaffRoleGroup::values();
        $assignedByGroup = StaffRoleAssignment::query()
            ->selectRaw('role_group, COUNT(DISTINCT staff_user_id) as total')
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today);
            })
            ->groupBy('role_group')
            ->pluck('total', 'role_group');

        $onLeaveIds = [];
        if (Schema::hasTable('staff_leave_requests')) {
            $onLeaveIds = StaffLeaveRequest::query()
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->pluck('staff_user_id')
                ->unique()
                ->values()
                ->all();
        }

        $onLeaveByGroup = [];
        if ($onLeaveIds !== []) {
            $onLeaveByGroup = StaffRoleAssignment::query()
                ->selectRaw('role_group, COUNT(DISTINCT staff_user_id) as total')
                ->where('status', 'active')
                ->whereIn('staff_user_id', $onLeaveIds)
                ->whereDate('starts_on', '<=', $today)
                ->where(function ($query) use ($today): void {
                    $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today);
                })
                ->groupBy('role_group')
                ->pluck('total', 'role_group')
                ->all();
        }

        $items = [];
        foreach ($groups as $group) {
            $assigned = (int) ($assignedByGroup[$group] ?? 0);
            $onLeave = (int) ($onLeaveByGroup[$group] ?? 0);
            $available = max(0, $assigned - $onLeave);

            $items[] = [
                'role_group' => $group,
                'label' => StaffRoleGroupLabels::label($group),
                'assigned' => $assigned,
                'on_leave' => $onLeave,
                'available' => $available,
                'coverage_ok' => $available > 0,
            ];
        }

        return $items;
    }
}
