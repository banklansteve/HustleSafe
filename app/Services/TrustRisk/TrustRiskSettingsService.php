<?php

namespace App\Services\TrustRisk;

use App\Models\AdminPlatformSetting;
use Illuminate\Support\Facades\Schema;

class TrustRiskSettingsService
{
    /**
     * @return array{low_max: int, medium_max: int, high_max: int, monitoring_queue_min_score: int}
     */
    public function thresholds(): array
    {
        $defaults = config('trust_risk.tier_thresholds', []);
        $queueDefault = (int) config('trust_risk.monitoring_queue_min_score', 40);

        if (! Schema::hasTable('admin_platform_settings')) {
            return [
                'low_max' => (int) ($defaults['low_max'] ?? 39),
                'medium_max' => (int) ($defaults['medium_max'] ?? 69),
                'high_max' => (int) ($defaults['high_max'] ?? 84),
                'monitoring_queue_min_score' => $queueDefault,
            ];
        }

        $stored = AdminPlatformSetting::query()
            ->whereIn('key', [
                'trust_risk.tier_low_max',
                'trust_risk.tier_medium_max',
                'trust_risk.tier_high_max',
                'trust_risk.monitoring_queue_min_score',
            ])
            ->get()
            ->keyBy('key');

        return [
            'low_max' => (int) ($stored->get('trust_risk.tier_low_max')?->value['value'] ?? $defaults['low_max'] ?? 39),
            'medium_max' => (int) ($stored->get('trust_risk.tier_medium_max')?->value['value'] ?? $defaults['medium_max'] ?? 69),
            'high_max' => (int) ($stored->get('trust_risk.tier_high_max')?->value['value'] ?? $defaults['high_max'] ?? 84),
            'monitoring_queue_min_score' => (int) ($stored->get('trust_risk.monitoring_queue_min_score')?->value['value'] ?? $queueDefault),
        ];
    }

    public function tierForScore(int $score): string
    {
        $t = $this->thresholds();

        if ($score <= $t['low_max']) {
            return 'low';
        }
        if ($score <= $t['medium_max']) {
            return 'medium';
        }
        if ($score <= $t['high_max']) {
            return 'high';
        }

        return 'critical';
    }

    /**
     * @return array<string, float>
     */
    public function weights(): array
    {
        return config('trust_risk.weights', []);
    }
}
