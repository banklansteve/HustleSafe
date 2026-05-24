<?php

namespace App\Support;

use App\Models\AdminPlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class PlatformSettings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('admin_platform_settings')) {
            return $default;
        }

        $value = Cache::remember(
            'platform_setting:'.md5($key),
            60,
            static function () use ($key, $default) {
                $record = AdminPlatformSetting::query()->where('key', $key)->first();
                if ($record === null) {
                    return $default;
                }

                return $record->value['value'] ?? $default;
            },
        );

        return $value ?? $default;
    }

    public static function float(string $key, float $default): float
    {
        return (float) self::get($key, $default);
    }

    public static function int(string $key, int $default): int
    {
        return (int) self::get($key, $default);
    }

    public static function bool(string $key, bool $default): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function platformFeePercent(): float
    {
        $percent = self::float('financial.platform_fee_percent', (float) config('payment.platform_fee_percent', 12));

        return max(0.0, min(100.0, $percent));
    }

    public static function escrowReleaseCooldownHours(): int
    {
        return max(0, min(720, self::int('financial.escrow_release_cooldown_hours', 24)));
    }

    public static function highValueReleaseAuthorizationMinor(): int
    {
        return max(0, self::int('financial.high_value_release_authorization_minor', 100_000_000));
    }

    public static function highValueQuestThresholdMinor(): int
    {
        return max(0, self::int('financial.high_value_quest_threshold_minor', (int) config('verification_engine.safeguards.high_value_arbitration_threshold_minor', 100_000_000)));
    }

    public static function forgetCache(?string $key = null): void
    {
        if ($key !== null) {
            Cache::forget('platform_setting:'.md5($key));

            return;
        }

        if (! Schema::hasTable('admin_platform_settings')) {
            return;
        }

        foreach (AdminPlatformSetting::query()->pluck('key') as $storedKey) {
            Cache::forget('platform_setting:'.md5((string) $storedKey));
        }
    }
}
