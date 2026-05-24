<?php

namespace App\Services\Admin;

use App\Models\AdminPlatformSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class MaintenanceModeService
{
    /**
     * Maintenance is controlled only via admin_platform_settings — never artisan down,
     * so the app can still boot, show the Inertia maintenance page, and allow admin access.
     */
    public function isEnabled(): bool
    {
        if (! Schema::hasTable('admin_platform_settings')) {
            return false;
        }

        $record = AdminPlatformSetting::query()->where('key', 'maintenance.enabled')->first();

        return $this->normalizeBoolean($record?->value['value'] ?? false);
    }

    public function message(): string
    {
        return (string) $this->settingValue(
            'maintenance.message',
            'We are tuning the workshop — HustleSafe will be back shortly.',
        );
    }

    public function returnTime(): ?string
    {
        $value = $this->settingValue('maintenance.return_time', '');

        return $value !== '' ? $value : null;
    }

    /**
     * @return array{enabled: bool, message: string, return_time: ?string, legacy_artisan_down: bool}
     */
    public function status(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'message' => $this->message(),
            'return_time' => $this->returnTime(),
            'legacy_artisan_down' => app()->isDownForMaintenance(),
        ];
    }

    public function enable(?string $message = null, ?string $returnTime = null): void
    {
        $this->persist(true, $message, $returnTime);
        $this->clearLegacyArtisanDown();
    }

    public function disable(): void
    {
        $this->persist(false);
        $this->clearLegacyArtisanDown();
    }

    public function clearLegacyArtisanDown(): void
    {
        try {
            Artisan::call('up');
        } catch (\Throwable) {
            // Continue — remove files directly below.
        }

        try {
            if (app()->maintenanceMode()->active()) {
                app()->maintenanceMode()->deactivate();
            }
        } catch (\Throwable) {
            // Continue — remove files directly below.
        }

        foreach ([
            storage_path('framework/maintenance.php'),
            storage_path('framework/down'),
        ] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public function persist(bool $enabled, ?string $message = null, ?string $returnTime = null): void
    {
        if (! Schema::hasTable('admin_platform_settings')) {
            return;
        }

        $this->upsert('maintenance.enabled', 'maintenance', $enabled);
        if ($message !== null) {
            $this->upsert('maintenance.message', 'maintenance', $message);
        }
        if ($returnTime !== null) {
            $this->upsert('maintenance.return_time', 'maintenance', $returnTime);
        }
    }

    private function upsert(string $key, string $section, mixed $value): void
    {
        AdminPlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'section' => $section,
                'value' => ['value' => $value],
                'is_sensitive' => false,
            ],
        );
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        if (! Schema::hasTable('admin_platform_settings')) {
            return $default;
        }

        $record = AdminPlatformSetting::query()->where('key', $key)->first();

        return $record?->value['value'] ?? $default;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
