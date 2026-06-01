<?php

namespace App\Services\Contracts;

use App\Enums\ContractStatus;
use App\Models\FreelancerDeliveryExtensionLog;
use App\Models\QuestContract;
use App\Models\User;
use App\Models\UserDeliveryMetric;
use App\Models\UserRiskProfile;
use App\Services\Admin\AdminActivityFeedService;
use Illuminate\Support\Facades\Schema;

class DeliveryExtensionPatternDetector
{
    public const PATTERN_THRESHOLD_PERCENT = 40;

    public const ROLLING_WINDOW_DAYS = 90;

    public function __construct(
        private readonly AdminActivityFeedService $activityFeed,
    ) {}

    /**
     * @return array{scanned: int, flagged: int}
     */
    public function run(): array
    {
        if (! Schema::hasTable('freelancer_delivery_extension_logs')) {
            return ['scanned' => 0, 'flagged' => 0];
        }

        $windowStart = now()->subDays(self::ROLLING_WINDOW_DAYS);
        $freelancerIds = QuestContract::query()
            ->where('status', ContractStatus::Active)
            ->distinct()
            ->pluck('freelancer_id');

        $flagged = 0;

        foreach ($freelancerIds as $freelancerId) {
            if ($this->evaluateFreelancer((int) $freelancerId, $windowStart)) {
                $flagged++;
            }
        }

        return ['scanned' => $freelancerIds->count(), 'flagged' => $flagged];
    }

    private function evaluateFreelancer(int $freelancerId, \Carbon\Carbon $windowStart): bool
    {
        $activeContracts = QuestContract::query()
            ->where('freelancer_id', $freelancerId)
            ->where('status', ContractStatus::Active)
            ->where('activated_at', '>=', $windowStart)
            ->pluck('id');

        if ($activeContracts->isEmpty()) {
            return false;
        }

        $contractsWithExtensions = FreelancerDeliveryExtensionLog::query()
            ->where('user_id', $freelancerId)
            ->whereIn('quest_contract_id', $activeContracts)
            ->where('logged_at', '>=', $windowStart)
            ->distinct('quest_contract_id')
            ->count('quest_contract_id');

        $percent = ($contractsWithExtensions / max(1, $activeContracts->count())) * 100;

        if ($percent <= self::PATTERN_THRESHOLD_PERCENT) {
            return false;
        }

        $user = User::query()->find($freelancerId);
        if ($user === null) {
            return false;
        }

        UserDeliveryMetric::query()->updateOrCreate(
            ['user_id' => $freelancerId],
            ['extension_pattern_flagged' => true, 'calculated_at' => now()],
        );

        if (Schema::hasTable('user_risk_profiles')) {
            $profile = UserRiskProfile::query()->firstOrNew(['user_id' => $freelancerId]);
            $signals = is_array($profile->signals) ? $profile->signals : [];
            $signals['repeated_delivery_extension_pattern'] = [
                'label' => 'Repeated Delivery Extension Pattern',
                'percent' => round($percent, 1),
                'active_contracts' => $activeContracts->count(),
                'contracts_with_extensions' => $contractsWithExtensions,
                'window_days' => self::ROLLING_WINDOW_DAYS,
                'detected_at' => now()->toIso8601String(),
            ];
            $profile->signals = $signals;
            $profile->in_risk_queue = true;
            $profile->queued_at = $profile->queued_at ?? now();
            $profile->save();
        }

        $this->activityFeed->record(
            category: 'trust',
            eventKey: 'freelancer.repeated_extension_pattern',
            title: __('Repeated delivery extension pattern — :name', ['name' => $user->name]),
            summary: __('Freelancer requested extensions on :pct% of active contracts in the last :days days.', [
                'pct' => number_format($percent, 1),
                'days' => self::ROLLING_WINDOW_DAYS,
            ]),
            entities: [
                ['type' => 'user', 'id' => $user->id, 'label' => $user->name],
            ],
            metadata: [
                'signal' => 'Repeated Delivery Extension Pattern',
                'percent' => round($percent, 1),
                'active_contracts' => $activeContracts->count(),
                'contracts_with_extensions' => $contractsWithExtensions,
            ],
            subjectType: User::class,
            subjectId: $user->id,
            severity: 'warning',
        );

        return true;
    }
}
