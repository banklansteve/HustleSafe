<?php

namespace App\Services\Admin;

use App\Enums\QuestBoostTier;
use App\Models\AdminFinancialLedgerEntry;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\QuestBoost;
use App\Support\NgnMoney;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class QuestBoostReportService
{
    /**
     * @return array<string, mixed>
     */
    public function report(Request $request): array
    {
        [$from, $to] = $this->resolveRange($request);
        $boosts = QuestBoost::query()
            ->with(['client:id,name,email', 'grantedByAdmin:id,name,email', 'quest.questCategory:id,name'])
            ->whereBetween('granted_at', [$from, $to])
            ->orderByDesc('granted_at')
            ->get();

        $byTier = collect(QuestBoostTier::ordered())->mapWithKeys(fn (QuestBoostTier $tier) => [
            $tier->value => $boosts->where('tier', $tier->value)->count(),
        ])->all();

        $byCategory = $boosts
            ->groupBy(fn (QuestBoost $b) => $b->quest?->questCategory?->name ?? 'Uncategorised')
            ->map(fn ($group, $category) => ['category' => $category, 'count' => $group->count()])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();

        $byAdmin = $boosts
            ->groupBy('granted_by_admin_id')
            ->map(fn ($group) => [
                'admin' => $group->first()?->grantedByAdmin?->name,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $weekly = $boosts
            ->groupBy(fn (QuestBoost $b) => $b->granted_at?->startOfWeek()->format('Y-m-d'))
            ->map(fn ($group, $week) => ['week' => $week, 'count' => $group->count()])
            ->sortKeys()
            ->values()
            ->all();

        return [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'total_boosts' => $boosts->count(),
                'total_investment_minor' => (int) $boosts->sum('planned_cost_minor'),
                'total_investment_display' => NgnMoney::format((int) $boosts->sum('planned_cost_minor')),
                'by_tier' => $byTier,
            ],
            'trend' => [
                'weekly' => $weekly,
                'by_admin' => $byAdmin,
            ],
            'category_breakdown' => $byCategory,
            'line_items' => $boosts->map(fn (QuestBoost $b) => [
                'reference' => $b->reference,
                'quest_id' => $b->quest_id,
                'quest_title' => $b->quest_title_snapshot,
                'client_name' => $b->client?->name,
                'tier' => $b->tierEnum()->label(),
                'planned_cost_minor' => (int) $b->planned_cost_minor,
                'planned_cost_display' => NgnMoney::format((int) $b->planned_cost_minor),
                'starts_at' => $b->starts_at?->toIso8601String(),
                'ends_at' => $b->ends_at?->toIso8601String(),
                'actual_duration_hours' => $b->actual_ended_at
                    ? round($b->starts_at->diffInMinutes($b->actual_ended_at) / 60, 1)
                    : round($b->starts_at->diffInMinutes($b->ends_at) / 60, 1),
                'grant_reason' => $b->grant_reason,
                'granting_admin' => $b->grantedByAdmin?->name,
                'status' => $b->statusEnum()->label(),
            ])->values()->all(),
        ];
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $payload = $this->report($request);
        $filename = 'quest-boosts-report-'.$payload['filters']['from'].'-to-'.$payload['filters']['to'].'.csv';

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Quest Boost Revenue Report']);
            fputcsv($out, ['Period', $payload['filters']['from'].' to '.$payload['filters']['to']]);
            fputcsv($out, ['Total boosts', $payload['summary']['total_boosts']]);
            fputcsv($out, ['Total theoretical investment (NGN)', NgnMoney::csvMajor((int) $payload['summary']['total_investment_minor'])]);
            fputcsv($out, []);
            fputcsv($out, [
                'Boost ID', 'Quest ID', 'Quest title', 'Client', 'Tier', 'Planned cost (NGN)',
                'Start', 'End', 'Actual duration (hours)', 'Grant reason', 'Granting admin', 'Status',
            ]);
            foreach ($payload['line_items'] as $row) {
                fputcsv($out, [
                    $row['reference'],
                    $row['quest_id'],
                    $row['quest_title'],
                    $row['client_name'],
                    $row['tier'],
                    NgnMoney::csvMajor((int) $row['planned_cost_minor']),
                    $row['starts_at'],
                    $row['ends_at'],
                    $row['actual_duration_hours'],
                    $row['grant_reason'],
                    $row['granting_admin'],
                    $row['status'],
                ]);
            }
            fclose($out);
        }, $filename);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
