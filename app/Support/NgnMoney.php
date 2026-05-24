<?php

namespace App\Support;

final class NgnMoney
{
    public static function format(int $minor): string
    {
        return '₦'.number_format($minor / 100, 2);
    }

    public static function toMinor(float|string $major): int
    {
        return (int) round(((float) $major) * 100);
    }

    public static function platformFeeMinor(int $grossMinor, ?float $percent = null): int
    {
        $percent ??= PlatformSettings::platformFeePercent();

        return (int) round($grossMinor * ($percent / 100));
    }

    public static function netAfterFee(int $grossMinor, ?float $percent = null): int
    {
        return max(0, $grossMinor - self::platformFeeMinor($grossMinor, $percent));
    }
}
