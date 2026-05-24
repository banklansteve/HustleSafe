<?php

namespace App\Services\Operations;

use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use Illuminate\Support\Facades\DB;

class StaffCategoryHealthService
{
    public function dashboard(): array
    {
        $since = now()->subDays(30);

        $categories = QuestCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);

        $questStats = Quest::query()
            ->select('quest_category_id', DB::raw('COUNT(*) as quest_count'), DB::raw('AVG(budget_amount_minor) as avg_budget'))
            ->where('created_at', '>=', $since)
            ->groupBy('quest_category_id')
            ->get()
            ->keyBy('quest_category_id');

        $proposalStats = QuestOffer::query()
            ->join('quests', 'quests.id', '=', 'quest_offers.quest_id')
            ->select('quests.quest_category_id', DB::raw('COUNT(*) as proposal_count'))
            ->where('quest_offers.created_at', '>=', $since)
            ->groupBy('quests.quest_category_id')
            ->get()
            ->keyBy('quest_category_id');

        $filledStats = Quest::query()
            ->select('quest_category_id', DB::raw('SUM(CASE WHEN freelancer_id IS NOT NULL THEN 1 ELSE 0 END) as filled'))
            ->where('created_at', '>=', $since)
            ->groupBy('quest_category_id')
            ->get()
            ->keyBy('quest_category_id');

        $disputeStats = QuestDispute::query()
            ->join('quests', 'quests.id', '=', 'quest_disputes.quest_id')
            ->select('quests.quest_category_id', DB::raw('COUNT(*) as dispute_count'))
            ->where('quest_disputes.created_at', '>=', $since)
            ->groupBy('quests.quest_category_id')
            ->get()
            ->keyBy('quest_category_id');

        return [
            'window_days' => 30,
            'items' => $categories->map(function (QuestCategory $cat) use ($questStats, $proposalStats, $filledStats, $disputeStats) {
                $quests = (int) ($questStats[$cat->id]->quest_count ?? 0);
                $proposals = (int) ($proposalStats[$cat->id]->proposal_count ?? 0);
                $filled = (int) ($filledStats[$cat->id]->filled ?? 0);
                $disputes = (int) ($disputeStats[$cat->id]->dispute_count ?? 0);
                $avgBudget = (int) ($questStats[$cat->id]->avg_budget ?? 0);

                $fillRate = $quests > 0 ? round(($filled / $quests) * 100, 1) : 0;
                $proposalRate = $quests > 0 ? round($proposals / $quests, 1) : 0;
                $disputeRate = $quests > 0 ? round(($disputes / $quests) * 100, 1) : 0;

                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'quest_volume' => $quests,
                    'proposal_rate' => $proposalRate,
                    'fill_rate' => $fillRate,
                    'dispute_rate' => $disputeRate,
                    'avg_contract_value_minor' => $avgBudget,
                    'health_flag' => $this->healthFlag($disputeRate, $proposalRate, $avgBudget, $quests),
                ];
            })->values()->all(),
        ];
    }

    private function healthFlag(float $disputeRate, float $proposalRate, int $avgBudget, int $quests): ?string
    {
        if ($quests < 3) {
            return null;
        }
        if ($disputeRate >= 15) {
            return 'high_disputes';
        }
        if ($proposalRate < 0.5) {
            return 'low_supply';
        }
        if ($avgBudget > 0 && $avgBudget < 500000) {
            return 'low_budget_cluster';
        }

        return null;
    }
}
