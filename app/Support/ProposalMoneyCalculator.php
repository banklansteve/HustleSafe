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
    public static function breakdown(?array $materials, ?array $pricing): ?array
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
        $stamp = max(0, (int) ($pricing['stamp_duty_ngn'] ?? 0)) * 100;
        $platform = max(0, (int) ($pricing['platform_fee_ngn'] ?? 0)) * 100;
        if ($platform === 0) {
            $baseForFee = $prof + $matMinor + $travel;
            $platform = (int) round($baseForFee * (PlatformSettings::platformFeePercent() / 100));
        }
        $discount = max(0, (int) ($pricing['discount_ngn'] ?? 0)) * 100;
        $baseMinor = $prof + $matMinor + $travel;
        $vatRate = (float) config('quests.proposal_vat_percent', 7.5);
        $vatApplies = filter_var($pricing['vat_applies'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $vatMinor = $vatApplies ? (int) round($baseMinor * ($vatRate / 100)) : 0;
        $whtPct = max(0.0, min(100.0, (float) ($pricing['withholding_tax_percent'] ?? 0)));
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
        $breakdown = self::breakdown($data['materials'], $p);
        if ($breakdown === null) {
            throw new \LogicException('Proposal pricing invalid.');
        }

        $vatRate = (float) config('quests.proposal_vat_percent', 7.5);
        $vatApplies = filter_var($p['vat_applies'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $whtPct = max(0.0, min(100.0, (float) ($p['withholding_tax_percent'] ?? 0)));

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
            'professional_fee_minor' => $breakdown['prof_minor'],
            'materials_total_minor' => $matMinor,
            'travel_cost_minor' => $breakdown['travel_minor'],
            'vat_minor' => $breakdown['vat_minor'],
            'vat_applies' => $vatApplies,
            'vat_percent' => $vatRate,
            'withholding_tax_minor' => $breakdown['wht_minor'],
            'withholding_tax_percent' => $whtPct,
            'stamp_duty_minor' => $breakdown['stamp_minor'],
            'platform_fee_minor' => $breakdown['platform_minor'],
            'discount_minor' => $breakdown['discount_minor'],
            'grand_total_minor' => $breakdown['grand_minor'],
            'grand_total_ngn' => (int) round($breakdown['grand_minor'] / 100),
            'terms' => $terms,
        ];

        return [
            'materials' => $materials,
            'pricing_snapshot' => $pricingSnapshot,
        ];
    }
}
