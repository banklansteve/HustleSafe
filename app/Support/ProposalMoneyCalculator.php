<?php

namespace App\Support;

/**
 * Single source for proposal totals (must match JS on Proposals/Create.vue).
 */
final class ProposalMoneyCalculator
{
    /**
     * Normalise incoming material rows: compute line costs and drop blank rows.
     *
     * @param  array<int, mixed>|null  $materials
     * @return list<array<string, mixed>>
     */
    public static function incomingMaterialRows(?array $materials): array
    {
        if (! is_array($materials)) {
            return [];
        }

        $out = [];
        foreach ($materials as $row) {
            if (! is_array($row)) {
                continue;
            }

            $qtyRaw = $row['quantity'] ?? '1';
            $qty = is_numeric($qtyRaw)
                ? (float) $qtyRaw
                : (float) str_replace(',', '.', preg_replace('/[^0-9.,\-]/', '', (string) $qtyRaw));
            if (! is_finite($qty) || $qty < 0) {
                $qty = 0.0;
            }

            if (array_key_exists('unit_price_ngn', $row)) {
                $unit = max(0, (int) $row['unit_price_ngn']);
                $row['cost_ngn'] = (int) round($qty * $unit);
            } elseif (! array_key_exists('cost_ngn', $row)) {
                $row['cost_ngn'] = 0;
            } else {
                $row['cost_ngn'] = max(0, (int) $row['cost_ngn']);
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '' && ((int) ($row['cost_ngn'] ?? 0)) < 1) {
                continue;
            }

            $row['label'] = $label !== '' ? $label : __('Materials / parts');
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $materials
     * @param  array<string, mixed>|null  $pricing
     * @return array{
     *     base_minor: int,
     *     prof_minor: int,
     *     mat_minor: int,
     *     travel_minor: int,
     *     vat_minor: int,
     *     wht_minor: int,
     *     stamp_minor: int,
     *     platform_minor: int,
     *     discount_minor: int,
     *     grand_minor: int
     * }|null
     */
    public static function breakdown(?array $materials, ?array $pricing, bool $includeClientCharges = true): ?array
    {
        if (! is_array($pricing)) {
            return null;
        }

        $matMinor = 0;
        if (is_array($materials)) {
            foreach ($materials as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $matMinor += max(0, (int) ($row['cost_ngn'] ?? 0)) * 100;
            }
        }

        $prof = max(0, (int) ($pricing['professional_fee_ngn'] ?? 0)) * 100;
        $travel = max(0, (int) ($pricing['travel_cost_ngn'] ?? 0)) * 100;
        $stamp = $includeClientCharges ? max(0, (int) ($pricing['stamp_duty_ngn'] ?? 0)) * 100 : 0;
        $platform = max(0, (int) ($pricing['platform_fee_ngn'] ?? 0)) * 100;
        if ($includeClientCharges && $platform === 0) {
            $baseForFee = $prof + $matMinor + $travel;
            $platform = (int) round($baseForFee * (PlatformSettings::platformFeePercent() / 100));
        } elseif (! $includeClientCharges) {
            $platform = 0;
        }

        $discount = max(0, (int) ($pricing['discount_ngn'] ?? 0)) * 100;
        $baseMinor = $prof + $matMinor + $travel;
        $vatRate = PlatformSettings::vatPercent();
        $vatApplies = $includeClientCharges
            && $vatRate > 0
            && filter_var($pricing['vat_applies'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $vatMinor = $vatApplies ? (int) round($baseMinor * ($vatRate / 100)) : 0;
        $whtPct = $includeClientCharges
            ? max(0.0, min(100.0, (float) ($pricing['withholding_tax_percent'] ?? 0)))
            : 0.0;
        $whtMinor = (int) round($baseMinor * ($whtPct / 100));
        $grandMinor = $baseMinor + $vatMinor + $whtMinor + $stamp + $platform - $discount;

        return [
            'base_minor' => $baseMinor,
            'prof_minor' => $prof,
            'mat_minor' => $matMinor,
            'travel_minor' => $travel,
            'vat_minor' => $vatMinor,
            'wht_minor' => $whtMinor,
            'stamp_minor' => $stamp,
            'platform_minor' => $platform,
            'discount_minor' => $discount,
            'grand_minor' => $grandMinor,
        ];
    }

    /**
     * Client escrow total from stored freelancer quote components.
     *
     * @param  array<string, mixed>  $quoteBreakdown
     * @return array<string, int|float|bool>
     */
    public static function clientEscrowBreakdown(array $quoteBreakdown): array
    {
        $baseMinor = (int) ($quoteBreakdown['base_minor'] ?? 0);
        $discount = (int) ($quoteBreakdown['discount_minor'] ?? 0);
        $platform = (int) round($baseMinor * (PlatformSettings::platformFeePercent() / 100));
        $vatRate = PlatformSettings::vatPercent();
        $vatMinor = $vatRate > 0 ? (int) round($baseMinor * ($vatRate / 100)) : 0;
        $whtMinor = 0;
        $stampMinor = 0;
        $grandMinor = $baseMinor + $vatMinor + $whtMinor + $stampMinor + $platform - $discount;

        return [
            'base_minor' => $baseMinor,
            'prof_minor' => (int) ($quoteBreakdown['prof_minor'] ?? 0),
            'mat_minor' => (int) ($quoteBreakdown['mat_minor'] ?? 0),
            'travel_minor' => (int) ($quoteBreakdown['travel_minor'] ?? 0),
            'vat_minor' => $vatMinor,
            'vat_applies' => $vatMinor > 0,
            'vat_percent' => $vatRate,
            'wht_minor' => $whtMinor,
            'stamp_minor' => $stampMinor,
            'platform_minor' => $platform,
            'discount_minor' => $discount,
            'quote_minor' => max(0, $baseMinor - $discount),
            'grand_minor' => $grandMinor,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $pricingSnapshot
     */
    public static function quoteTotalMinor(?array $pricingSnapshot): int
    {
        if (! is_array($pricingSnapshot)) {
            return 0;
        }

        if (isset($pricingSnapshot['quote_total_minor'])) {
            return max(0, (int) $pricingSnapshot['quote_total_minor']);
        }

        $base = (int) ($pricingSnapshot['professional_fee_minor'] ?? 0)
            + (int) ($pricingSnapshot['materials_total_minor'] ?? 0)
            + (int) ($pricingSnapshot['travel_cost_minor'] ?? 0);
        $discount = (int) ($pricingSnapshot['discount_minor'] ?? 0);

        return max(0, $base - $discount);
    }

    /**
     * Amount credited to the freelancer wallet on escrow release (quote less discount).
     * Platform fee and VAT are funded by the client on top of this amount.
     *
     * @param  array<string, mixed>|null  $pricingSnapshot
     */
    public static function freelancerWalletPayoutMinor(?array $pricingSnapshot): int
    {
        return self::quoteTotalMinor($pricingSnapshot);
    }

    /**
     * @param  array<string, mixed>|null  $pricingSnapshot
     */
    public static function freelancerInstallmentPayoutMinor(?array $pricingSnapshot, int $installmentCount): int
    {
        $total = self::freelancerWalletPayoutMinor($pricingSnapshot);
        if ($installmentCount < 1) {
            return $total;
        }

        return (int) floor($total / $installmentCount);
    }

    /**
     * @param  array<string, mixed>|null  $pricingSnapshot
     * @return array{
     *     professional_fee_minor: int,
     *     materials_minor: int,
     *     travel_minor: int,
     *     discount_minor: int,
     *     quote_minor: int
     * }
     */
    public static function freelancerQuoteComponents(?array $pricingSnapshot): array
    {
        if (! is_array($pricingSnapshot)) {
            return [
                'professional_fee_minor' => 0,
                'materials_minor' => 0,
                'travel_minor' => 0,
                'discount_minor' => 0,
                'quote_minor' => 0,
            ];
        }

        $prof = (int) ($pricingSnapshot['professional_fee_minor'] ?? 0);
        $mat = (int) ($pricingSnapshot['materials_total_minor'] ?? 0);
        $travel = (int) ($pricingSnapshot['travel_cost_minor'] ?? 0);
        $discount = (int) ($pricingSnapshot['discount_minor'] ?? 0);

        return [
            'professional_fee_minor' => $prof,
            'materials_minor' => $mat,
            'travel_minor' => $travel,
            'discount_minor' => $discount,
            'quote_minor' => max(0, $prof + $mat + $travel - $discount),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $pricingSnapshot
     */
    public static function escrowTotalMinor(?array $pricingSnapshot): int
    {
        if (! is_array($pricingSnapshot)) {
            return 0;
        }

        if (isset($pricingSnapshot['escrow_total_minor'])) {
            return max(0, (int) $pricingSnapshot['escrow_total_minor']);
        }

        return max(0, (int) ($pricingSnapshot['grand_total_minor'] ?? 0));
    }

    /**
     * @param  array{materials: list<array<string, mixed>>, pricing: array<string, mixed>}  $data
     * @return array{materials: list<array<string, mixed>>, pricing_snapshot: array<string, mixed>}
     */
    public static function normalizedPayload(array $data, bool $isUpdate = false): array
    {
        $materials = [];
        foreach ($data['materials'] ?? [] as $row) {
            $qty = isset($row['quantity']) ? (string) $row['quantity'] : null;
            $unitNgn = array_key_exists('unit_price_ngn', $row) ? max(0, (int) $row['unit_price_ngn']) : null;
            $lineMinor = max(0, (int) $row['cost_ngn']) * 100;
            $entry = [
                'label' => (string) $row['label'],
                'quantity' => $qty,
                'line_total_minor' => $lineMinor,
                'cost_minor' => $lineMinor,
            ];
            if ($unitNgn !== null) {
                $entry['unit_price_minor'] = $unitNgn * 100;
            }
            $materials[] = $entry;
        }

        $p = $data['pricing'];
        $matMinor = array_sum(array_column($materials, 'line_total_minor'));
        $quoteBreakdown = self::breakdown($data['materials'], $p, includeClientCharges: false);
        if ($quoteBreakdown === null) {
            throw new \LogicException('Proposal pricing invalid.');
        }

        $clientBreakdown = self::clientEscrowBreakdown($quoteBreakdown);
        $vatRate = (float) ($clientBreakdown['vat_percent'] ?? PlatformSettings::vatPercent());
        $vatApplies = (bool) ($clientBreakdown['vat_applies'] ?? false);
        $whtPct = 0.0;

        $terms = $isUpdate
            ? [
                'last_revision_at' => now()->timezone('Africa/Lagos')->toIso8601String(),
                'document' => 'terms_of_service',
            ]
            : [
                'accepted' => true,
                'accepted_at' => now()->timezone('Africa/Lagos')->toIso8601String(),
                'document' => 'terms_of_service',
            ];

        $pricingSnapshot = [
            'professional_fee_minor' => $quoteBreakdown['prof_minor'],
            'materials_total_minor' => $matMinor,
            'travel_cost_minor' => $quoteBreakdown['travel_minor'],
            'quote_total_minor' => $quoteBreakdown['grand_minor'],
            'vat_minor' => $clientBreakdown['vat_minor'],
            'vat_applies' => $vatApplies,
            'vat_percent' => $vatRate,
            'withholding_tax_minor' => $clientBreakdown['wht_minor'],
            'withholding_tax_percent' => $whtPct,
            'stamp_duty_minor' => $clientBreakdown['stamp_minor'],
            'platform_fee_minor' => $clientBreakdown['platform_minor'],
            'discount_minor' => $quoteBreakdown['discount_minor'],
            'escrow_total_minor' => $clientBreakdown['grand_minor'],
            'grand_total_minor' => $clientBreakdown['grand_minor'],
            'grand_total_ngn' => (int) round($clientBreakdown['grand_minor'] / 100),
            'terms' => $terms,
        ];

        return [
            'materials' => $materials,
            'pricing_snapshot' => $pricingSnapshot,
        ];
    }
}
