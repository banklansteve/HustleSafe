<?php

namespace App\Services\Admin\ContractManagement;

use App\Enums\ContractPatrolFlagStatus;
use App\Enums\ContractPatrolFlagType;
use App\Enums\ContractStatus;
use App\Models\ContractPatrolFlag;
use App\Models\QuestContract;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class ContractPatrolAnomalyService
{
    public function flagsAvailable(): bool
    {
        return Schema::hasTable('contract_patrol_flags');
    }

    public function scanAll(): int
    {
        if (! $this->flagsAvailable()) {
            return 0;
        }

        $created = 0;
        QuestContract::query()
            ->with(['quest', 'activeDispute'])
            ->whereIn('status', [
                ContractStatus::PendingEscrow,
                ContractStatus::Active,
                ContractStatus::AmendmentPending,
                ContractStatus::Disputed,
            ])
            ->orderByDesc('id')
            ->chunkById(200, function ($contracts) use (&$created): void {
                foreach ($contracts as $contract) {
                    $created += $this->scanContract($contract);
                }
            });

        return $created;
    }

    public function scanContract(QuestContract $contract): int
    {
        if (! $this->flagsAvailable()) {
            return 0;
        }

        $contract->loadMissing(['quest', 'activeDispute', 'freelancer']);
        $created = 0;

        if ($contract->status === ContractStatus::Disputed || $contract->active_dispute_id) {
            $created += $this->upsertFlag(
                $contract,
                ContractPatrolFlagType::ActiveDispute,
                'Active dispute — escalated to super admin review',
                'critical',
                ['escalated_to_super_admin' => true],
            ) ? 1 : 0;
            $created += $this->flagRefundBeforeWorkStarts($contract) ? 1 : 0;
        }

        if ($contract->flagged_for_review) {
            $created += $this->upsertFlag(
                $contract,
                ContractPatrolFlagType::FlaggedForReview,
                (string) ($contract->flagged_for_review_reason ?? 'Flagged for staff review'),
            ) ? 1 : 0;
        }

        if ($contract->status === ContractStatus::AmendmentPending) {
            $created += $this->upsertFlag($contract, ContractPatrolFlagType::AmendmentPending, 'Contract amendment awaiting party response') ? 1 : 0;
        }

        if ($contract->status === ContractStatus::PendingEscrow) {
            $staleHours = (int) config('contract_management.patrol.pending_escrow_stale_hours', 72);
            $generated = $contract->generated_at ?? $contract->created_at;
            if ($generated !== null && $generated->lt(now()->subHours($staleHours))) {
                $created += $this->upsertFlag($contract, ContractPatrolFlagType::PendingEscrowStale, 'Escrow funding window exceeded') ? 1 : 0;
            }
        }

        $quest = $contract->quest;
        if ($quest !== null) {
            if ($quest->delivery_acknowledged_at !== null) {
                $this->clearOverdueDeliveryFlags($contract);
            }

            if ($quest->latest_delivery_submission_id !== null
                && $quest->delivery_acknowledged_at === null
                && $quest->delivery_revision_requested_at === null) {
                $created += $this->upsertFlag($contract, ContractPatrolFlagType::DeliveryAwaitingReview, 'Delivery submitted — awaiting client approval') ? 1 : 0;
            }

            if ($quest->release_hold_until !== null || $quest->release_hold_reason) {
                $created += $this->upsertFlag($contract, ContractPatrolFlagType::EscrowHoldActive, 'Release hold: '.($quest->release_hold_reason ?? 'admin hold')) ? 1 : 0;
            }
        }

        if ($contract->status === ContractStatus::Active
            && $contract->agreed_delivery_date !== null
            && ($quest === null || $quest->latest_delivery_submission_id === null)) {
            $created += $this->flagTieredOverdueDelivery($contract, $quest) ? 1 : 0;
        }

        if ($contract->status === ContractStatus::Active && $contract->escrow_funded_at !== null) {
            $created += $this->flagFreelancerInactiveAfterAward($contract) ? 1 : 0;
        }

        return $created;
    }

    public function clearOverdueDeliveryFlags(QuestContract $contract): void
    {
        if (! $this->flagsAvailable()) {
            return;
        }

        ContractPatrolFlag::query()
            ->where('quest_contract_id', $contract->id)
            ->whereIn('flag_type', [
                ContractPatrolFlagType::OverdueDelivery->value,
                ContractPatrolFlagType::OverdueDeliveryMedium->value,
                ContractPatrolFlagType::OverdueDeliveryCritical->value,
            ])
            ->whereIn('status', [ContractPatrolFlagStatus::Open, ContractPatrolFlagStatus::Acknowledged])
            ->update([
                'status' => ContractPatrolFlagStatus::Dismissed,
                'dismissed_at' => now(),
                'dismissal_reason' => 'Delivery approved — auto-cleared',
                'resolved_at' => now(),
            ]);
    }

    private function flagTieredOverdueDelivery(QuestContract $contract, ?\App\Models\Quest $quest): bool
    {
        $due = $contract->agreed_delivery_date;
        if ($due === null) {
            return false;
        }

        $deadline = $due->copy()->endOfDay();
        if (now()->lte($deadline)) {
            return false;
        }

        $hoursOverdue = (int) $deadline->diffInHours(now());
        $mediumHours = (int) config('contract_management.patrol.overdue_delivery_medium_hours', 24);
        $criticalHours = (int) config('contract_management.patrol.overdue_delivery_critical_hours', 72);
        $days = $due->diffInDays(now());
        $created = false;

        if ($hoursOverdue >= $mediumHours) {
            $created = $this->upsertFlag(
                $contract,
                ContractPatrolFlagType::OverdueDeliveryMedium,
                "Overdue {$days} day(s) — notify both parties",
                'medium',
                ['hours_overdue' => $hoursOverdue, 'days_overdue' => $days],
            ) || $created;
        }

        if ($hoursOverdue >= $criticalHours) {
            $created = $this->upsertFlag(
                $contract,
                ContractPatrolFlagType::OverdueDeliveryCritical,
                "Overdue {$days} day(s) — assign to super admin",
                'critical',
                ['hours_overdue' => $hoursOverdue, 'days_overdue' => $days, 'escalated_to_super_admin' => true],
            ) || $created;
        }

        return $created;
    }

    private function flagRefundBeforeWorkStarts(QuestContract $contract): bool
    {
        $quest = $contract->quest;
        $dispute = $contract->activeDispute;
        if ($quest === null || $dispute === null) {
            return false;
        }

        if ($quest->latest_delivery_submission_id !== null) {
            return false;
        }

        $requestedOutcome = (string) ($dispute->structured_intake['requested_outcome'] ?? '');
        if (! in_array($requestedOutcome, ['full_refund', 'partial_refund'], true)) {
            return false;
        }

        return $this->upsertFlag(
            $contract,
            ContractPatrolFlagType::RefundBeforeWorkStarts,
            'Client requested refund before any delivery was submitted',
            'high',
            [
                'dispute_id' => $dispute->id,
                'requested_outcome' => $requestedOutcome,
            ],
        );
    }

    private function flagFreelancerInactiveAfterAward(QuestContract $contract): bool
    {
        $freelancer = $contract->freelancer;
        $awardAt = $contract->activated_at ?? $contract->escrow_funded_at ?? $contract->created_at;
        if ($freelancer === null || $awardAt === null) {
            return false;
        }

        $inactiveHours = (int) config('contract_management.patrol.freelancer_inactive_after_award_hours', 48);
        if (now()->lt($awardAt->copy()->addHours($inactiveHours))) {
            return false;
        }

        $lastActive = $freelancer->last_active_at;
        if ($lastActive !== null && $lastActive->gte(now()->subHours($inactiveHours))) {
            return false;
        }

        $quest = $contract->quest;
        if ($quest?->latest_delivery_submission_id !== null) {
            return false;
        }

        return $this->upsertFlag(
            $contract,
            ContractPatrolFlagType::FreelancerInactiveAfterAward,
            "Freelancer has had no platform activity for {$inactiveHours}+ hours after award",
            'medium',
            [
                'freelancer_id' => $freelancer->id,
                'award_at' => $awardAt->toIso8601String(),
                'last_active_at' => $lastActive?->toIso8601String(),
                'inactive_hours' => $inactiveHours,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function upsertFlag(
        QuestContract $contract,
        ContractPatrolFlagType $type,
        string $summary,
        ?string $severity = null,
        array $meta = [],
    ): bool {
        $fingerprint = 'contract:'.$contract->id.':'.$type->value;
        $existing = ContractPatrolFlag::query()->where('fingerprint', $fingerprint)->first();

        if ($existing && ! in_array($existing->status, [ContractPatrolFlagStatus::Open, ContractPatrolFlagStatus::Acknowledged], true)) {
            return false;
        }

        if ($existing) {
            $existing->forceFill([
                'summary' => $summary,
                'severity' => $severity ?? $type->defaultSeverity(),
                'detected_at' => now(),
                'meta' => array_merge($existing->meta ?? [], $meta, ['last_scan_at' => now()->toIso8601String()]),
            ])->save();

            return false;
        }

        ContractPatrolFlag::query()->create([
            'quest_contract_id' => $contract->id,
            'flag_type' => $type,
            'severity' => $severity ?? $type->defaultSeverity(),
            'status' => ContractPatrolFlagStatus::Open,
            'fingerprint' => $fingerprint,
            'summary' => $summary,
            'meta' => $meta,
            'detected_at' => now(),
        ]);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openFlags(int $limit = 50, ?string $severity = null): array
    {
        if (! $this->flagsAvailable()) {
            return [];
        }

        return ContractPatrolFlag::query()
            ->with(['contract:id,reference_code,quest_id', 'contract.quest:id,title'])
            ->whereIn('status', [ContractPatrolFlagStatus::Open, ContractPatrolFlagStatus::Acknowledged])
            ->when($severity, fn ($q) => $q->where('severity', $severity))
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('detected_at')
            ->limit($limit)
            ->get()
            ->map(fn (ContractPatrolFlag $flag) => [
                'id' => $flag->id,
                'contract_id' => $flag->quest_contract_id,
                'reference_code' => $flag->contract?->reference_code,
                'quest_title' => $flag->contract?->quest?->title,
                'type' => $flag->flag_type instanceof ContractPatrolFlagType ? $flag->flag_type->value : (string) $flag->flag_type,
                'type_label' => $flag->flag_type instanceof ContractPatrolFlagType ? $flag->flag_type->label() : (string) $flag->flag_type,
                'severity' => $flag->severity,
                'status' => $flag->status instanceof ContractPatrolFlagStatus ? $flag->status->value : (string) $flag->status,
                'summary' => $flag->summary,
                'detected_at' => $flag->detected_at?->timezone('Africa/Lagos')->toIso8601String(),
                'detected_ago' => $flag->detected_at?->diffForHumans(),
            ])
            ->all();
    }

    public function acknowledge(ContractPatrolFlag $flag, User $staff): ContractPatrolFlag
    {
        $flag->update([
            'status' => ContractPatrolFlagStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by_id' => $staff->id,
            'assigned_to_id' => $flag->assigned_to_id ?? $staff->id,
        ]);

        return $flag->fresh();
    }

    public function dismiss(ContractPatrolFlag $flag, \App\Models\User $staff, string $reason): ContractPatrolFlag
    {
        $flag->update([
            'status' => ContractPatrolFlagStatus::Dismissed,
            'dismissed_at' => now(),
            'dismissed_by_id' => $staff->id,
            'dismissal_reason' => $reason,
        ]);

        return $flag->fresh();
    }
}
