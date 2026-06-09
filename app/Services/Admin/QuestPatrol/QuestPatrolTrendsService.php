<?php

namespace App\Services\Admin\QuestPatrol;

use App\Enums\QuestPatrolFlagType;
use App\Models\ModerationApprovalRequest;
use App\Models\QuestPatrolFlag;
use App\Models\QuestPatrolInvestigation;
use Illuminate\Support\Facades\Schema;

final class QuestPatrolTrendsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        if (! Schema::hasTable('quest_patrol_flags')) {
            return $this->emptySummary();
        }

        $since = now()->subDays(7);
        $flags = QuestPatrolFlag::query()->where('detected_at', '>=', $since)->get();
        $total = max(1, $flags->count());
        $dismissed = $flags->where('status', 'dismissed')->count();
        $resolved = $flags->where('status', 'resolved')->count();
        $openHigh = QuestPatrolFlag::query()->where('status', 'open')->where('severity', 'high')->count();

        $topTypes = $flags->groupBy('flag_type')
            ->map(fn ($group, $type) => [
                'type' => $type,
                'label' => QuestPatrolFlagType::tryFrom($type)?->label() ?? $type,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->all();

        $pendingApprovals = Schema::hasTable('moderation_approval_requests')
            ? ModerationApprovalRequest::query()->where('status', 'pending')->count()
            : 0;

        return [
            'top_anomaly_types' => $topTypes,
            'false_positive_rate_percent' => round(($dismissed / $total) * 100, 1),
            'action_rate_percent' => round((($dismissed + $resolved) / $total) * 100, 1),
            'open_high_severity' => $openHigh,
            'flags_this_week' => $flags->count(),
            'pending_approval_requests' => $pendingApprovals,
            'open_investigations' => QuestPatrolInvestigation::query()->where('status', 'open')->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openCases(int $limit = 15): array
    {
        return app(QuestPatrolInvestigationService::class)->openCases($limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingApprovals(): array
    {
        if (! Schema::hasTable('moderation_approval_requests')) {
            return [];
        }

        return ModerationApprovalRequest::query()
            ->where('status', 'pending')
            ->with('requester:id,name,email')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (ModerationApprovalRequest $row) => [
                'id' => $row->id,
                'request_type' => $row->request_type,
                'subject_type' => $row->subject_type,
                'subject_id' => $row->subject_id,
                'reason' => $row->reason,
                'requester' => $row->requester?->only(['id', 'name', 'email']),
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'top_anomaly_types' => [],
            'false_positive_rate_percent' => 0,
            'action_rate_percent' => 0,
            'open_high_severity' => 0,
            'flags_this_week' => 0,
            'pending_approval_requests' => 0,
            'open_investigations' => 0,
        ];
    }
}
