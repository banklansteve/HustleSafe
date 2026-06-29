<?php

namespace App\Services\Disputes;

use App\Enums\QuestDisputeManagementStatus;
use App\Models\DisputeEvent;
use App\Models\QuestDispute;
use App\Models\User;

class DisputeAutoAssignmentService
{
    public function autoAssign(QuestDispute $dispute): ?User
    {
        if ($dispute->assigned_staff_id !== null) {
            return $dispute->assignedStaff;
        }

        $staffId = $this->pickStaffIdRoundRobin();
        if ($staffId === null) {
            return null;
        }

        $staff = User::query()->find($staffId);
        if ($staff === null) {
            return null;
        }

        $dispute->forceFill([
            'assigned_staff_id' => $staff->id,
            'staff_claimed_at' => now(),
            'management_status' => QuestDisputeManagementStatus::Open,
            'severity' => $this->severityForAmount((int) $dispute->disputed_amount_minor),
        ])->save();

        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => null,
            'action' => 'management.auto_assigned',
            'properties' => [
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'method' => 'round_robin',
            ],
            'created_at' => now(),
        ]);

        app(DisputeStaffAlertService::class)->notifyAssigned($dispute->fresh(), $staff);

        return $staff;
    }

    public function severityForAmount(int $amountMinor): string
    {
        $critical = (int) config('disputes.management.critical_amount_minor', 1_000_000_00);
        $high = (int) config('disputes.management.high_amount_minor', 500_000_00);
        $medium = (int) config('disputes.management.medium_amount_minor', 100_000_00);

        if ($amountMinor >= $critical) {
            return 'critical';
        }
        if ($amountMinor >= $high) {
            return 'high';
        }
        if ($amountMinor >= $medium) {
            return 'medium';
        }

        return 'low';
    }

    protected function pickStaffIdRoundRobin(): ?int
    {
        $staffIds = $this->assignableStaffAdminQuery()
            ->orderBy('id')
            ->pluck('id')
            ->values();

        if ($staffIds->isEmpty()) {
            return null;
        }

        $overload = (int) config('disputes.management.staff_overload_threshold', 15);
        $loads = QuestDispute::query()
            ->whereIn('assigned_staff_id', $staffIds)
            ->whereIn('management_status', [
                QuestDisputeManagementStatus::Open->value,
                QuestDisputeManagementStatus::PendingResponse->value,
                QuestDisputeManagementStatus::UnderReview->value,
                QuestDisputeManagementStatus::ReadyForDecision->value,
            ])
            ->selectRaw('assigned_staff_id, COUNT(*) as load_count')
            ->groupBy('assigned_staff_id')
            ->pluck('load_count', 'assigned_staff_id');

        $eligible = $staffIds->filter(fn (int $id): bool => (int) ($loads[$id] ?? 0) < $overload)->values();
        if ($eligible->isEmpty()) {
            $eligible = $staffIds;
        }

        $lastEvent = DisputeEvent::query()
            ->where('action', 'management.auto_assigned')
            ->latest('id')
            ->first();
        $lastId = (int) data_get($lastEvent?->properties, 'staff_id', 0);
        $lastIndex = $eligible->search($lastId);
        $nextIndex = $lastIndex === false ? 0 : ($lastIndex + 1) % $eligible->count();

        return (int) $eligible[$nextIndex];
    }

    /** @deprecated Use pickStaffIdRoundRobin */
    protected function pickStaffId(): ?int
    {
        return $this->pickStaffIdRoundRobin();
    }

    /**
     * @return list<array{id: int, name: string, active_load: int}>
     */
    public function staffOptions(): array
    {
        $staffIds = $this->assignableStaffAdminQuery()
            ->orderBy('name')
            ->get(['id', 'name']);

        $loads = QuestDispute::query()
            ->whereIn('assigned_staff_id', $staffIds->pluck('id'))
            ->whereIn('management_status', [
                QuestDisputeManagementStatus::Open->value,
                QuestDisputeManagementStatus::PendingResponse->value,
                QuestDisputeManagementStatus::UnderReview->value,
                QuestDisputeManagementStatus::ReadyForDecision->value,
            ])
            ->selectRaw('assigned_staff_id, COUNT(*) as load_count')
            ->groupBy('assigned_staff_id')
            ->pluck('load_count', 'assigned_staff_id');

        return $staffIds->map(fn (User $user): array => [
            'id' => (int) $user->id,
            'name' => $user->name,
            'active_load' => (int) ($loads[$user->id] ?? 0),
        ])->values()->all();
    }

    /**
     * Staff admins eligible for dispute assignment (no `is_active` column on users).
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    protected function assignableStaffAdminQuery()
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->whereNull('suspended_at')
            ->whereNull('under_review_at')
            ->whereNull('banned_at')
            ->whereNull('deactivated_at');
    }
}
