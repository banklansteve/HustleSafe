<?php

namespace App\Services\Contracts;

use App\Enums\ContractStatus;
use App\Models\QuestContract;
use App\Models\User;
use App\Models\UserDeliveryMetric;
use App\Models\UserRiskProfile;
use App\Services\Admin\AdminActivityFeedService;
use Illuminate\Support\Facades\Schema;

class DeliveryReliabilityScoreService
{
    public const LOW_RELIABILITY_THRESHOLD = 60;

    public const MIN_COMPLETED_FOR_FLAG = 10;

    public function __construct(
        private readonly AdminActivityFeedService $activityFeed,
    ) {}

    public function recalculate(User $user): UserDeliveryMetric
    {
        $originalDate = Schema::hasColumn('quest_contracts', 'original_agreed_delivery_date')
            ? 'original_agreed_delivery_date'
            : null;

        $completed = QuestContract::query()
            ->where('freelancer_id', $user->id)
            ->where('status', ContractStatus::Completed)
            ->get(['id', 'agreed_delivery_date', 'original_agreed_delivery_date', 'completed_at']);

        $total = $completed->count();
        $onTime = $completed->filter(function (QuestContract $contract) use ($originalDate): bool {
            $deadline = $contract->original_agreed_delivery_date ?? $contract->agreed_delivery_date;
            if ($deadline === null || $contract->completed_at === null) {
                return true;
            }

            return $contract->completed_at->lte($deadline->copy()->endOfDay());
        })->count();

        $score = $total > 0 ? round(($onTime / $total) * 100, 2) : null;
        $lowFlag = $total >= self::MIN_COMPLETED_FOR_FLAG
            && $score !== null
            && $score < self::LOW_RELIABILITY_THRESHOLD;

        $metric = UserDeliveryMetric::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'on_time_completed_count' => $onTime,
                'total_completed_count' => $total,
                'reliability_score' => $score,
                'low_reliability_flagged' => $lowFlag,
                'calculated_at' => now(),
            ],
        );

        if ($lowFlag) {
            $this->surfaceLowReliability($user, $metric);
        }

        return $metric;
    }

    /**
     * @return array{score: ?float, on_time: int, total: int, low_reliability_flagged: bool}|null
     */
    public function snapshot(User $user): ?array
    {
        if (! Schema::hasTable('user_delivery_metrics')) {
            return null;
        }

        $metric = UserDeliveryMetric::query()->where('user_id', $user->id)->first();
        if ($metric === null) {
            $metric = $this->recalculate($user);
        }

        return [
            'score' => $metric->reliability_score !== null ? (float) $metric->reliability_score : null,
            'on_time' => (int) $metric->on_time_completed_count,
            'total' => (int) $metric->total_completed_count,
            'low_reliability_flagged' => (bool) $metric->low_reliability_flagged,
        ];
    }

    private function surfaceLowReliability(User $user, UserDeliveryMetric $metric): void
    {
        $this->activityFeed->record(
            category: 'trust',
            eventKey: 'freelancer.low_delivery_reliability',
            title: __('Low delivery reliability — :name', ['name' => $user->name]),
            summary: __('Freelancer delivery reliability is :score% across :total completed contracts.', [
                'score' => number_format((float) $metric->reliability_score, 1),
                'total' => $metric->total_completed_count,
            ]),
            entities: [
                ['type' => 'user', 'id' => $user->id, 'label' => $user->name],
            ],
            metadata: [
                'signal' => 'Low Delivery Reliability',
                'reliability_score' => $metric->reliability_score,
                'on_time_completed_count' => $metric->on_time_completed_count,
                'total_completed_count' => $metric->total_completed_count,
            ],
            subjectType: User::class,
            subjectId: $user->id,
            severity: 'warning',
        );

        if (Schema::hasTable('user_risk_profiles')) {
            $profile = UserRiskProfile::query()->firstOrNew(['user_id' => $user->id]);
            $signals = is_array($profile->signals) ? $profile->signals : [];
            $signals['low_delivery_reliability'] = [
                'label' => 'Low Delivery Reliability',
                'score' => $metric->reliability_score,
                'total_completed' => $metric->total_completed_count,
                'detected_at' => now()->toIso8601String(),
            ];
            $profile->signals = $signals;
            $profile->in_risk_queue = true;
            $profile->queued_at = $profile->queued_at ?? now();
            $profile->save();
        }
    }
}
