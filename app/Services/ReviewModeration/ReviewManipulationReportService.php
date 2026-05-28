<?php

namespace App\Services\ReviewModeration;

use App\Enums\ReviewStatus;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\Review;
use App\Models\ReviewManipulationReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewManipulationReportService
{
    public function refreshAll(): void
    {
        $date = now()->toDateString();

        ReviewManipulationReport::query()->updateOrCreate(
            ['report_type' => 'freelancer_concentration', 'report_date' => $date],
            [
                'payload' => ['rows' => $this->buildFreelancerConcentrationRows()],
                'generated_at' => now(),
            ],
        );

        ReviewManipulationReport::query()->updateOrCreate(
            ['report_type' => 'client_pattern', 'report_date' => $date],
            [
                'payload' => ['rows' => $this->buildClientPatternRows()],
                'generated_at' => now(),
            ],
        );
    }

    public function latest(string $reportType): ?array
    {
        $report = ReviewManipulationReport::query()
            ->where('report_type', $reportType)
            ->orderByDesc('report_date')
            ->first();

        if ($report === null) {
            return [
                'report_date' => now()->toDateString(),
                'generated_at' => now()->toIso8601String(),
                'rows' => $reportType === 'freelancer_concentration'
                    ? $this->buildFreelancerConcentrationRows()
                    : $this->buildClientPatternRows(),
            ];
        }

        return [
            'report_date' => $report->report_date?->toDateString(),
            'generated_at' => $report->generated_at?->toIso8601String(),
            'rows' => $report->payload['rows'] ?? [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFreelancerConcentrationRows(): array
    {
        $cfg = config('review_moderation.manipulation');
        $youngDays = (int) ($cfg['freelancer_young_account_days'] ?? 45);
        $minContracts = (int) ($cfg['freelancer_min_prior_contracts'] ?? 3);

        $published = Review::query()
            ->where('status', ReviewStatus::Published)
            ->whereNotNull('rating')
            ->where('reviewer_party', 'client')
            ->select(['id', 'reviewee_id', 'reviewer_id', 'rating', 'created_at'])
            ->get();

        $grouped = $published->groupBy('reviewee_id');
        $rows = [];

        foreach ($grouped as $freelancerId => $reviews) {
            $total = $reviews->count();
            if ($total < 3) {
                continue;
            }

            $suspicious = $reviews->filter(function (Review $review) use ($youngDays, $minContracts): bool {
                $reviewer = User::query()->find($review->reviewer_id);
                if ($reviewer === null) {
                    return true;
                }

                $young = $reviewer->created_at?->gte(now()->subDays($youngDays)) ?? true;
                $lowContracts = $this->completedContractCount($reviewer->id) < $minContracts;

                return $young || $lowContracts;
            });

            $pct = round(($suspicious->count() / $total) * 100, 1);
            $tier = $pct > 40 ? 'red' : ($pct >= 20 ? 'amber' : 'normal');

            $freelancer = User::query()->find($freelancerId);
            $rows[] = [
                'freelancer_id' => (int) $freelancerId,
                'freelancer_name' => $freelancer?->name ?? 'User #'.$freelancerId,
                'total_reviews' => $total,
                'suspicious_count' => $suspicious->count(),
                'concentration_pct' => $pct,
                'risk_tier' => $tier,
                'review_ids' => $suspicious->pluck('id')->values()->all(),
                'reviewer_ids' => $suspicious->pluck('reviewer_id')->unique()->values()->all(),
            ];
        }

        usort($rows, fn ($a, $b) => $b['concentration_pct'] <=> $a['concentration_pct']);

        return array_slice($rows, 0, 100);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildClientPatternRows(): array
    {
        $cfg = config('review_moderation.manipulation');
        $windowDays = (int) ($cfg['client_low_rating_window_days'] ?? 60);
        $minFreelancers = (int) ($cfg['client_min_distinct_freelancers'] ?? 4);
        $since = now()->subDays($windowDays);

        $lowReviews = Review::query()
            ->where('reviewer_party', 'client')
            ->where('status', ReviewStatus::Published)
            ->whereBetween('rating', [1, 2])
            ->where('created_at', '>=', $since)
            ->get();

        $byClient = $lowReviews->groupBy('reviewer_id');
        $rows = [];

        foreach ($byClient as $clientId => $reviews) {
            $distinctFreelancers = $reviews->pluck('reviewee_id')->unique();
            if ($distinctFreelancers->count() < $minFreelancers) {
                continue;
            }

            $totalClientReviews = Review::query()
                ->where('reviewer_id', $clientId)
                ->where('reviewer_party', 'client')
                ->where('status', ReviewStatus::Published)
                ->where('created_at', '>=', $since)
                ->count();

            $lowCount = $reviews->count();
            $ratio = $totalClientReviews > 0 ? round(($lowCount / $totalClientReviews) * 100, 1) : 100.0;

            $disputeCount = QuestDispute::query()
                ->where('opened_by_user_id', $clientId)
                ->where('created_at', '>=', $since)
                ->count();

            $client = User::query()->find($clientId);
            $rows[] = [
                'client_id' => (int) $clientId,
                'client_name' => $client?->name ?? 'User #'.$clientId,
                'low_rating_count' => $lowCount,
                'total_ratings_window' => $totalClientReviews,
                'low_rating_ratio_pct' => $ratio,
                'distinct_freelancers_low' => $distinctFreelancers->count(),
                'disputes_filed' => $disputeCount,
                'no_dispute_low_ratings' => max(0, $lowCount - min($lowCount, $disputeCount)),
                'flagged' => true,
                'freelancer_ids' => $distinctFreelancers->values()->all(),
                'review_ids' => $reviews->pluck('id')->values()->all(),
            ];
        }

        usort($rows, fn ($a, $b) => $b['low_rating_ratio_pct'] <=> $a['low_rating_ratio_pct']);

        return array_slice($rows, 0, 100);
    }

    private function completedContractCount(int $userId): int
    {
        return (int) Quest::query()
            ->where(fn ($q) => $q->where('client_id', $userId)->orWhere('freelancer_id', $userId))
            ->whereNotNull('completed_at')
            ->count();
    }

    /**
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportCsv(string $reportType)
    {
        $data = $this->latest($reportType);
        $filename = $reportType.'-'.($data['report_date'] ?? now()->toDateString()).'.csv';

        return response()->streamDownload(function () use ($data, $reportType): void {
            $out = fopen('php://output', 'w');
            if ($reportType === 'freelancer_concentration') {
                fputcsv($out, ['freelancer_id', 'freelancer_name', 'total_reviews', 'suspicious_count', 'concentration_pct', 'risk_tier']);
                foreach ($data['rows'] as $row) {
                    fputcsv($out, [
                        $row['freelancer_id'],
                        $row['freelancer_name'],
                        $row['total_reviews'],
                        $row['suspicious_count'],
                        $row['concentration_pct'],
                        $row['risk_tier'],
                    ]);
                }
            } else {
                fputcsv($out, ['client_id', 'client_name', 'low_rating_count', 'total_ratings_window', 'low_rating_ratio_pct', 'distinct_freelancers_low', 'disputes_filed', 'no_dispute_low_ratings']);
                foreach ($data['rows'] as $row) {
                    fputcsv($out, [
                        $row['client_id'],
                        $row['client_name'],
                        $row['low_rating_count'],
                        $row['total_ratings_window'],
                        $row['low_rating_ratio_pct'],
                        $row['distinct_freelancers_low'],
                        $row['disputes_filed'],
                        $row['no_dispute_low_ratings'],
                    ]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
