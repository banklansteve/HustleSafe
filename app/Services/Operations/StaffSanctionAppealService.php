<?php

namespace App\Services\Operations;

use App\Models\AdminActivityLog;
use App\Models\AdminUserSanction;
use App\Models\StaffSanctionAppeal;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Services\AdminActivityLogger;
use Illuminate\Validation\ValidationException;

class StaffSanctionAppealService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function listing(): array
    {
        return [
            'items' => StaffSanctionAppeal::query()
                ->with(['user:id,name,email', 'sanction'])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (StaffSanctionAppeal $appeal) => $this->row($appeal))
                ->all(),
        ];
    }

    public function detail(StaffSanctionAppeal $appeal): array
    {
        $appeal->load(['user', 'sanction.admin:id,name']);

        $history = AdminUserSanction::query()
            ->where('user_id', $appeal->user_id)
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (AdminUserSanction $s) => [
                'id' => $s->id,
                'type' => $s->type,
                'reason_code' => $s->reason_code,
                'notes' => $s->notes,
                'starts_at' => $s->starts_at?->toIso8601String(),
                'ends_at' => $s->ends_at?->toIso8601String(),
                'reversed_at' => $s->reversed_at?->toIso8601String(),
            ]);

        $activity = AdminActivityLog::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $appeal->user_id)
            ->latest()
            ->limit(20)
            ->get(['id', 'action', 'created_at']);

        return [
            'appeal' => $this->row($appeal),
            'statement' => $appeal->statement,
            'evidence' => $appeal->evidence ?? [],
            'sanction' => [
                'id' => $appeal->sanction?->id,
                'type' => $appeal->sanction?->type,
                'reason_code' => $appeal->sanction?->reason_code,
                'notes' => $appeal->sanction?->notes,
                'starts_at' => $appeal->sanction?->starts_at?->toIso8601String(),
                'ends_at' => $appeal->sanction?->ends_at?->toIso8601String(),
                'applied_by' => $appeal->sanction?->admin?->name,
            ],
            'sanction_history' => $history,
            'account_activity' => $activity->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        ];
    }

    public function approve(StaffSanctionAppeal $appeal, User $staff, string $note): void
    {
        if ($appeal->status !== 'pending' && $appeal->status !== 'investigating') {
            throw ValidationException::withMessages(['appeal' => 'Appeal is already resolved.']);
        }

        $sanction = $appeal->sanction;
        if ($sanction && ! $sanction->reversed_at) {
            $sanction->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $staff->id,
                'reversal_reason' => $note,
            ])->save();
        }

        $appeal->forceFill([
            'status' => 'approved',
            'reviewed_by_staff_id' => $staff->id,
            'decision_note' => $note,
            'resolved_at' => now(),
        ])->save();

        $appeal->user?->notify(new AdminUserMessageNotification(
            'Your sanction appeal was approved',
            $note,
        ));

        $this->logger->log($staff, 'staff_sanction_appeal.approved', StaffSanctionAppeal::class, $appeal->id, []);
    }

    public function reject(StaffSanctionAppeal $appeal, User $staff, string $note): void
    {
        if ($appeal->status !== 'pending' && $appeal->status !== 'investigating') {
            throw ValidationException::withMessages(['appeal' => 'Appeal is already resolved.']);
        }

        $appeal->forceFill([
            'status' => 'rejected',
            'reviewed_by_staff_id' => $staff->id,
            'decision_note' => $note,
            'resolved_at' => now(),
        ])->save();

        $appeal->user?->notify(new AdminUserMessageNotification(
            'Your sanction appeal was reviewed',
            $note,
        ));

        $this->logger->log($staff, 'staff_sanction_appeal.rejected', StaffSanctionAppeal::class, $appeal->id, []);
    }

    public function escalate(StaffSanctionAppeal $appeal, User $staff, string $note): void
    {
        $appeal->forceFill([
            'status' => 'escalated',
            'escalated_to_admin_id' => $staff->id,
            'decision_note' => $note,
        ])->save();

        $this->logger->log($staff, 'staff_sanction_appeal.escalated', StaffSanctionAppeal::class, $appeal->id, []);
    }

    private function row(StaffSanctionAppeal $appeal): array
    {
        return [
            'id' => $appeal->id,
            'status' => $appeal->status,
            'user' => $appeal->user ? ['id' => $appeal->user->id, 'name' => $appeal->user->name, 'email' => $appeal->user->email] : null,
            'sanction_type' => $appeal->sanction?->type,
            'reason_code' => $appeal->sanction?->reason_code,
            'statement_excerpt' => str($appeal->statement)->limit(120)->toString(),
            'created_at' => $appeal->created_at?->toIso8601String(),
        ];
    }
}
