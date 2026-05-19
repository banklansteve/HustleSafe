<?php

namespace App\Support;

final class RoleSessionLifetime
{
    public const SUPER_ADMIN_MINUTES = 720;

    public const OPERATIONS_STAFF_MINUTES = 300;

    public const DEFAULT_MINUTES = 10080;

    public static function minutesForRole(?string $roleSlug): int
    {
        return match ($roleSlug) {
            'super_admin' => self::SUPER_ADMIN_MINUTES,
            'admin' => self::OPERATIONS_STAFF_MINUTES,
            default => self::DEFAULT_MINUTES,
        };
    }

    public static function applyForRole(?string $roleSlug): int
    {
        $minutes = self::minutesForRole($roleSlug);
        config(['session.lifetime' => $minutes]);

        return $minutes;
    }
}
