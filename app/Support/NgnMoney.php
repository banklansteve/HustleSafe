<?php

namespace App\Support;

final class NgnMoney
{
    public static function format(int $minor): string
    {
        return '₦'.number_format($minor / 100, 2);
    }

    /** Plain decimal for CSV/Excel — no symbol, no thousands separators. */
    public static function csvMajor(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
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

    public static function vatOnPlatformFeeMinor(int $platformFeeMinor, ?float $vatPercent = null): int
    {
        $vatPercent ??= PlatformSettings::vatPercent();

        return (int) round($platformFeeMinor * ($vatPercent / 100));
    }

    /**
     * @return array{platform_fee_minor: int, vat_minor: int, platform_revenue_minor: int, freelancer_net_minor: int}
     */
    public static function escrowReleaseBreakdown(int $grossMinor, ?float $feePercent = null, ?float $vatPercent = null): array
    {
        $platformFeeMinor = self::platformFeeMinor($grossMinor, $feePercent);
        $vatMinor = self::vatOnPlatformFeeMinor($platformFeeMinor, $vatPercent);
        $platformRevenueMinor = max(0, $platformFeeMinor - $vatMinor);
        $freelancerNetMinor = max(0, $grossMinor - $platformFeeMinor);

        return [
            'platform_fee_minor' => $platformFeeMinor,
            'vat_minor' => $vatMinor,
            'platform_revenue_minor' => $platformRevenueMinor,
            'freelancer_net_minor' => $freelancerNetMinor,
        ];
    }
}
