<?php

namespace App\Services\Admin\ContractManagement;

use App\Models\AdminPlatformSetting;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ContractManagementSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $definitions = config('contract_management.settings', []);
        $settings = [];

        foreach ($definitions as $key => $meta) {
            $platformKey = $this->platformKey($key);
            $stored = $this->storedValue($platformKey, $meta['default'] ?? null);
            $settings[] = [
                'key' => $key,
                'platform_key' => $platformKey,
                'label' => $meta['label'] ?? $key,
                'value' => $stored,
                'default' => $meta['default'] ?? null,
                'min' => $meta['min'] ?? null,
                'max' => $meta['max'] ?? null,
            ];
        }

        return [
            'settings' => $settings,
            'auto_release_hours' => PlatformSettings::escrowAutoReleaseHours(),
            'platform_fee_percent' => PlatformSettings::platformFeePercent(),
        ];
    }

    /**
     * @param  array<string, int|float>  $values
     */
    public function update(array $values, User $staff, Request $request): void
    {
        if (! Schema::hasTable('admin_platform_settings')) {
            throw ValidationException::withMessages(['settings' => __('Platform settings table is not available.')]);
        }

        $definitions = config('contract_management.settings', []);

        foreach ($definitions as $key => $meta) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = (int) $values[$key];
            $min = (int) ($meta['min'] ?? 0);
            $max = (int) ($meta['max'] ?? PHP_INT_MAX);
            if ($value < $min || $value > $max) {
                throw ValidationException::withMessages([
                    $key => __('Value must be between :min and :max.', ['min' => $min, 'max' => $max]),
                ]);
            }

            $platformKey = $this->platformKey($key);
            AdminPlatformSetting::query()->updateOrCreate(
                ['key' => $platformKey],
                [
                    'section' => 'contract_management',
                    'value' => ['value' => $value],
                    'updated_by_admin_id' => $staff->id,
                ],
            );
            PlatformSettings::forgetCache($platformKey);
        }
    }

    public function riskThreshold(string $level): int
    {
        $definitions = config('contract_management.settings', []);
        $key = $level === 'critical' ? 'critical_risk_threshold' : 'high_risk_threshold';
        $platformKey = $this->platformKey($key);
        $default = (int) ($definitions[$key]['default'] ?? ($level === 'critical' ? 75 : 50));

        return max(1, (int) PlatformSettings::get($platformKey, $default));
    }

    private function platformKey(string $key): string
    {
        return match ($key) {
            'auto_release_hours' => 'financial.auto_release_hours',
            'max_revision_requests' => 'contract_management.max_revision_requests',
            'dispute_evidence_deadline_hours' => 'contract_management.dispute_evidence_deadline_hours',
            'dispute_resolution_days' => 'contract_management.dispute_resolution_days',
            'critical_risk_threshold' => 'contract_management.critical_risk_threshold',
            'high_risk_threshold' => 'contract_management.high_risk_threshold',
            default => 'contract_management.'.$key,
        };
    }

    private function storedValue(string $platformKey, mixed $default): mixed
    {
        return PlatformSettings::get($platformKey, $default);
    }
}
