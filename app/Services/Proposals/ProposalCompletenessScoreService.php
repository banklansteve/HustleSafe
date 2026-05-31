<?php

namespace App\Services\Proposals;

use App\Models\QuestOffer;

class ProposalCompletenessScoreService
{
    public function score(QuestOffer $offer): int
    {
        $points = 0;

        $pitch = trim(strip_tags((string) ($offer->pitch ?? '')));
        if (strlen($pitch) >= 40) {
            $points += 15;
        }
        if (strlen($pitch) >= 120) {
            $points += 5;
        }

        $scope = trim(strip_tags((string) ($offer->scope_detail ?? '')));
        if (strlen($scope) >= 80) {
            $points += 20;
        }
        if (strlen($scope) >= 200) {
            $points += 5;
        }

        if (trim((string) ($offer->warranty_terms ?? '')) !== '') {
            $points += 10;
        }

        if ($offer->planned_start_date !== null && $offer->planned_finish_date !== null) {
            $points += 15;
        } elseif ($offer->estimated_duration_days !== null) {
            $points += 8;
        }

        if ($offer->progress_report_frequency) {
            $points += 5;
        }

        $materials = $offer->materials;
        if (is_array($materials) && count($materials) > 0) {
            $points += 10;
        }

        if ((int) ($offer->quoted_amount_minor ?? 0) > 0) {
            $points += 10;
        }

        $pricing = $offer->pricing_snapshot;
        if (is_array($pricing) && ! empty($pricing['professional_fee_minor'])) {
            $points += 5;
        }

        if ($offer->corrections_included !== null) {
            $points += 5;
        }

        return min(100, $points);
    }
}
