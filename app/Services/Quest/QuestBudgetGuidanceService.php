<?php

namespace App\Services\Quest;

use App\Enums\QuestStatus;
use App\Models\Quest;
use Illuminate\Support\Collection;

class QuestBudgetGuidanceService
{
    public const MIN_SAMPLE = 5;

    /**
     * @return array{sample_size: int, min_minor: int, max_minor: int, avg_minor: int, p25_minor: int, p75_minor: int, message: string|null}
     */
    public function forCategoryId(?int $categoryId): array
    {
        if (! $categoryId) {
            return $this->emptyPayload();
        }

        $statuses = [
            QuestStatus::Open->value,
            QuestStatus::Assigned->value,
            QuestStatus::InProgress->value,
            QuestStatus::Completed->value,
        ];

        $budgets = Quest::query()
            ->where('quest_category_id', $categoryId)
            ->whereIn('status', $statuses)
            ->whereNotNull('budget_amount_minor')
            ->where('budget_amount_minor', '>=', 10000)
            ->orderBy('budget_amount_minor')
            ->pluck('budget_amount_minor')
            ->map(fn ($v) => (int) $v)
            ->values();

        $sampleSize = $budgets->count();
        if ($sampleSize < self::MIN_SAMPLE) {
            return $this->emptyPayload();
        }

        $min = (int) $budgets->first();
        $max = (int) $budgets->last();
        $avg = (int) round($budgets->avg());
        $p25 = (int) $budgets->get((int) floor(($sampleSize - 1) * 0.25));
        $p75 = (int) $budgets->get((int) floor(($sampleSize - 1) * 0.75));

        $rangeLow = min($p25, $p75);
        $rangeHigh = max($p25, $p75);

        return [
            'sample_size' => $sampleSize,
            'min_minor' => $min,
            'max_minor' => $max,
            'avg_minor' => $avg,
            'p25_minor' => $p25,
            'p75_minor' => $p75,
            'message' => __('Similar quests on this platform typically budget :low–:high.', [
                'low' => $this->formatNgn($rangeLow),
                'high' => $this->formatNgn($rangeHigh),
            ]),
        ];
    }

    /**
     * @return array{sample_size: int, min_minor: int, max_minor: int, avg_minor: int, p25_minor: int, p75_minor: int, message: string|null}
     */
    private function emptyPayload(): array
    {
        return [
            'sample_size' => 0,
            'min_minor' => 0,
            'max_minor' => 0,
            'avg_minor' => 0,
            'p25_minor' => 0,
            'p75_minor' => 0,
            'message' => null,
        ];
    }

    private function formatNgn(int $minor): string
    {
        return '₦'.number_format($minor / 100, 0);
    }
}
