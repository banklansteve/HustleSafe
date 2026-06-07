<?php

namespace App\Support;

final class RoleSessionLifetime
{
    /** Production default — shorten via SUPER_ADMIN_SESSION_LIFETIME in .env when ready. */
    public const SUPER_ADMIN_MINUTES_PRODUCTION = 20160; // 14 days

    /** Local / development idle timeout when env is not set. */
    public const SUPER_ADMIN_MINUTES_LOCAL = 10080; // 7 days

    public const OPERATIONS_STAFF_MINUTES = 300;

    public const DEFAULT_MINUTES = 10080;

    public static function minutesForRole(?string $roleSlug): int
    {
        return match ($roleSlug) {
            'super_admin' => self::superAdminMinutes(),
            'admin' => self::operationsStaffMinutes(),
            default => self::DEFAULT_MINUTES,
        };
    }

    public static function superAdminMinutes(): int
    {
        $configured = env('SUPER_ADMIN_SESSION_LIFETIME');

        if ($configured !== null && $configured !== '') {
            return max(1, (int) $configured);
        }

        return app()->environment('production')
            ? self::SUPER_ADMIN_MINUTES_PRODUCTION
            : self::SUPER_ADMIN_MINUTES_LOCAL;
    }

    public static function operationsStaffMinutes(): int
    {
        $configured = env('ADMIN_SESSION_LIFETIME');

        if ($configured !== null && $configured !== '') {
            return max(1, (int) $configured);
        }

        return self::OPERATIONS_STAFF_MINUTES;
    }

    public static function applyForRole(?string $roleSlug): int
    {
        $minutes = self::minutesForRole($roleSlug);
        config(['session.lifetime' => $minutes]);

        return $minutes;
    }
}
