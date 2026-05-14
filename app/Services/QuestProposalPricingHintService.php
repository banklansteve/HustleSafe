<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;

class QuestProposalPricingHintService
{
    /**
     * @return array{
     *     sample_size: int,
     *     scope: 'self_category'|'market_category'|'none',
     *     professional_fee_ngn: int|null,
     *     materials_total_ngn: int|null,
     *     travel_cost_ngn: int|null,
     *     summary: string|null,
     * }
     */
    public function hintsFor(User $freelancer, Quest $quest): array
    {
        $catId = (int) ($quest->quest_category_id ?? 0);
        if ($catId < 1) {
            return $this->emptyHints();
        }

        $self = $this->aggregateFromOffers(
            QuestOffer::query()
                ->where('freelancer_id', $freelancer->id)
                ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
                ->whereHas('quest', fn ($q) => $q->where('quest_category_id', $catId))
                ->latest('updated_at')
                ->limit(30)
        );

        if ($self['n'] >= 2) {
            return $this->shapeHints($self, 'self_category');
        }

        $market = $this->aggregateFromOffers(
            QuestOffer::query()
                ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
                ->where('freelancer_id', '<>', $freelancer->id)
                ->whereHas('quest', fn ($q) => $q->where('quest_category_id', $catId))
                ->latest('updated_at')
                ->limit(60)
        );

        if ($market['n'] >= 3) {
            return $this->shapeHints($market, 'market_category');
        }

        return $this->emptyHints();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<QuestOffer>  $query
     * @return array{n: int, prof: float, mat: float, travel: float}
     */
    protected function aggregateFromOffers($query): array
    {
        $prof = [];
        $mat = [];
        $travel = [];

        foreach ($query->get(['pricing_snapshot']) as $offer) {
            $snap = $offer->pricing_snapshot;
            if (! is_array($snap)) {
                continue;
            }
            $p = (int) ($snap['professional_fee_minor'] ?? 0);
            $m = (int) ($snap['materials_total_minor'] ?? 0);
            $t = (int) ($snap['travel_cost_minor'] ?? 0);
            if ($p > 0) {
                $prof[] = $p;
            }
            if ($m > 0) {
                $mat[] = $m;
            }
            if ($t > 0) {
                $travel[] = $t;
            }
        }

        $n = count($prof);

        return [
            'n' => $n,
            'prof' => $n > 0 ? array_sum($prof) / $n : 0.0,
            'mat' => count($mat) > 0 ? array_sum($mat) / count($mat) : 0.0,
            'travel' => count($travel) > 0 ? array_sum($travel) / count($travel) : 0.0,
        ];
    }

    /**
     * @param  array{n: int, prof: float, mat: float, travel: float}  $agg
     * @return array<string, mixed>
     */
    protected function shapeHints(array $agg, string $scope): array
    {
        $feeNgn = $agg['prof'] > 0 ? (int) round($agg['prof'] / 100) : null;
        $matNgn = $agg['mat'] > 0 ? (int) round($agg['mat'] / 100) : null;
        $travelNgn = $agg['travel'] > 0 ? (int) round($agg['travel'] / 100) : null;

        $summary = match ($scope) {
            'self_category' => __('Based on your last :n proposals in this category.', ['n' => $agg['n']]),
            'market_category' => __('Based on recent marketplace proposals in this category (sample :n).', ['n' => $agg['n']]),
            default => null,
        };

        return [
            'sample_size' => $agg['n'],
            'scope' => $scope,
            'professional_fee_ngn' => $feeNgn,
            'materials_total_ngn' => $matNgn,
            'travel_cost_ngn' => $travelNgn,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyHints(): array
    {
        return [
            'sample_size' => 0,
            'scope' => 'none',
            'professional_fee_ngn' => null,
            'materials_total_ngn' => null,
            'travel_cost_ngn' => null,
            'summary' => null,
        ];
    }
}
