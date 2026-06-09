<?php

namespace App\Support;

final class RoleSessionLifetime
{
    /** Default super-admin idle timeout (14 days). Override via SUPER_ADMIN_SESSION_LIFETIME in .env. */
    public const SUPER_ADMIN_MINUTES = 20160;

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

        return max(1, (int) config('session.super_admin_lifetime', self::SUPER_ADMIN_MINUTES));
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
