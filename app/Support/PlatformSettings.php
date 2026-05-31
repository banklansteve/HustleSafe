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

    /**
     * @return array{min: int, max: int, default: int, extension_max: int, warning_hours: int}
     */
    public static function proposalDeadlineBounds(): array
    {
        $min = max(1, self::int('quests.proposal_deadline_min_days', 1));
        $max = max($min, self::int('quests.proposal_deadline_max_days', 60));
        $default = self::int('quests.proposal_deadline_default_days', self::int('quests.expiry_default_days', 14));
        $default = max($min, min($max, $default));

        return [
            'min' => $min,
            'max' => $max,
            'default' => $default,
            'extension_max' => max(1, self::int('quests.proposal_deadline_extension_max_days', 14)),
            'warning_hours' => max(1, self::int('quests.proposal_deadline_warning_hours', 48)),
        ];
    }

    public static function clampProposalDeadlineDays(int $days): int
    {
        $bounds = self::proposalDeadlineBounds();

        return max($bounds['min'], min($bounds['max'], $days));
    }

    /**
     * @return array{max_per_quest: int, award_nudge_days_after_deadline: int, no_shortlist_review_days: int}
     */
    public static function shortlistSettings(): array
    {
        return [
            'max_per_quest' => max(1, self::int('quests.shortlist_max_per_quest', 5)),
            'award_nudge_days_after_deadline' => max(1, self::int('quests.shortlist_award_nudge_days_after_deadline', 7)),
            'no_shortlist_review_days' => max(1, self::int('quests.proposals_no_shortlist_review_days', 5)),
        ];
    }

    /**
     * @return array{suspend_threshold: int, ban_threshold: int, suspend_duration_weeks: int}
     */
    public static function conversationMonitoringSanctions(): array
    {
        return [
            'suspend_threshold' => max(1, self::int('conversation_monitoring.suspend_flag_threshold', 3)),
            'ban_threshold' => max(1, self::int('conversation_monitoring.ban_flag_threshold', 5)),
            'suspend_duration_weeks' => max(1, self::int('conversation_monitoring.suspend_duration_weeks', 4)),
        ];
    }

    public static function contractEscrowFundingHours(): int
    {
        return max(1, min(168, self::int('contracts.escrow_funding_hours', 48)));
    }

    public static function contractAutoReleaseGraceDays(): int
    {
        return max(1, min(30, self::int('contracts.auto_release_grace_days', 5)));
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
