<?php

namespace App\Services\Admin\QuestPatrol;

use App\Models\QuestPatrolInvestigation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class QuestPatrolInvestigationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function open(string $subjectType, int $subjectId, User $admin, array $data): QuestPatrolInvestigation
    {
        $existing = QuestPatrolInvestigation::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return $existing;
        }

        return QuestPatrolInvestigation::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'status' => 'open',
            'opened_by_id' => $admin->id,
            'assigned_to_id' => $data['assigned_to_id'] ?? $admin->id,
            'title' => (string) ($data['title'] ?? 'Patrol investigation'),
            'severity' => (string) ($data['severity'] ?? 'medium'),
            'timeline' => [[
                'at' => now()->toIso8601String(),
                'actor_id' => $admin->id,
                'actor_name' => $admin->name,
                'note' => (string) ($data['note'] ?? 'Investigation opened from moderation patrol.'),
            ]],
            'meta' => [
                'linked_flag_ids' => $data['flag_ids'] ?? [],
                'reason_code' => $data['reason_code'] ?? null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forSubject(string $subjectType, int $subjectId): ?array
    {
        if (! Schema::hasTable('quest_patrol_investigations')) {
            return null;
        }

        $case = QuestPatrolInvestigation::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', 'open')
            ->with(['openedBy:id,name', 'assignedTo:id,name'])
            ->latest('id')
            ->first();

        return $case ? $this->payload($case) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openCases(int $limit = 15): array
    {
        if (! Schema::hasTable('quest_patrol_investigations')) {
            return [];
        }

        return QuestPatrolInvestigation::query()
            ->where('status', 'open')
            ->with(['openedBy:id,name', 'assignedTo:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (QuestPatrolInvestigation $case) => $this->payload($case))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addNote(QuestPatrolInvestigation $case, User $admin, array $data): QuestPatrolInvestigation
    {
        $timeline = $case->timeline ?? [];
        $timeline[] = [
            'at' => now()->toIso8601String(),
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'note' => (string) ($data['note'] ?? ''),
        ];

        $case->forceFill(['timeline' => $timeline])->save();

        return $case->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolve(QuestPatrolInvestigation $case, User $admin, array $data): QuestPatrolInvestigation
    {
        $timeline = $case->timeline ?? [];
        $timeline[] = [
            'at' => now()->toIso8601String(),
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'note' => 'Resolved: '.((string) ($data['note'] ?? 'Case closed.')),
        ];

        $case->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_id' => $admin->id,
            'timeline' => $timeline,
        ])->save();

        return $case->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(QuestPatrolInvestigation $case): array
    {
        return [
            'id' => $case->id,
            'case_reference' => $case->case_reference,
            'subject_type' => $case->subject_type,
            'subject_id' => $case->subject_id,
            'status' => $case->status,
            'title' => $case->title,
            'severity' => $case->severity,
            'timeline' => $case->timeline ?? [],
            'meta' => $case->meta ?? [],
            'opened_by' => $case->openedBy?->only(['id', 'name']),
            'assigned_to' => $case->assignedTo?->only(['id', 'name']),
            'created_at' => $case->created_at?->toIso8601String(),
            'resolved_at' => $case->resolved_at?->toIso8601String(),
        ];
    }
}
