<?php

namespace App\Support;

/**
 * Customer-facing fee copy and admin disclosure notes.
 * Platform fee % is always read from Platform Settings (financial.platform_fee_percent).
 */
final class PlatformFeeDisclosure
{
    public static function platformFeePercent(): float
    {
        return PlatformSettings::platformFeePercent();
    }

    public static function vatPercent(): float
    {
        return PlatformSettings::vatPercent();
    }

    public static function formatPercent(?float $percent = null): string
    {
        $value = $percent ?? self::platformFeePercent();

        return rtrim(rtrim(number_format(max(0.0, min(100.0, $value)), 2, '.', ''), '0'), '.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(?float $platformFeePercent = null): array
    {
        $percent = $platformFeePercent ?? self::platformFeePercent();
        $vat = self::vatPercent();

        return [
            'platform_fee_percent' => $percent,
            'platform_fee_percent_label' => self::formatPercent($percent),
            'vat_percent' => $vat,
            'vat_percent_label' => self::formatPercent($vat),
            'paystack_funding_fee' => '1.5% of escrow fund + ₦100, capped at ₦2,000',
            'paystack_payout_fee' => '₦10–₦50 depending on bank',
            'platform_fee_line' => sprintf('Platform fee: %s%% of the job amount', self::formatPercent($percent)),
            'vat_line' => sprintf('VAT on platform fee: %s%% (applies to platform fee only)', self::formatPercent($vat)),
            'covers' => [
                'Paystack gateway fees on escrow funding',
                'VAT on the platform fee',
                'Platform operations and support',
            ],
            'settings_path' => 'Super Admin → Platform Settings → Financial & Escrow → Platform fee (%)',
        ];
    }

    /**
     * Plain lines for emails and compact UI.
     *
     * @return list<string>
     */
    public static function summaryLines(?float $platformFeePercent = null): array
    {
        $d = self::toArray($platformFeePercent);

        return [
            'Paystack fee (client escrow funding): '.$d['paystack_funding_fee'],
            'Paystack payout fee (freelancer withdrawal): '.$d['paystack_payout_fee'],
            $d['vat_line'],
            $d['platform_fee_line'],
            'This covers: all gateway fees, VAT, and platform operation.',
        ];
    }
}
