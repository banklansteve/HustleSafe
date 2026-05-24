<?php

namespace App\Services\Operations;

use App\Models\Review;
use App\Models\StaffReviewIntegrityCase;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StaffReviewIntegrityService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function listing(): array
    {
        $live = $this->detectPatterns();
        $stored = StaffReviewIntegrityCase::query()
            ->with('subject:id,name,email')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (StaffReviewIntegrityCase $case) => $this->caseRow($case));

        return [
            'patterns' => config('operations_extended.review_integrity_patterns'),
            'live_signals' => $live,
            'cases' => $stored,
        ];
    }

    public function detail(StaffReviewIntegrityCase $case): array
    {
        $case->load('subject:id,name,email,trust_score,client_trust_score');

        $reviewIds = $case->flagged_review_ids ?? [];
        $reviews = $reviewIds === []
            ? collect()
            : Review::query()->with(['reviewer:id,name', 'reviewee:id,name', 'quest:id,title'])->whereIn('id', $reviewIds)->get();

        return [
            'case' => $this->caseRow($case),
            'reviews' => $reviews->map(fn (Review $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'title' => $r->title,
                'reviewer' => $r->reviewer?->name,
                'reviewee' => $r->reviewee?->name,
                'quest' => $r->quest?->title,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
            'pattern_data' => $case->pattern_data ?? [],
        ];
    }

    public function openCase(User $staff, array $data): StaffReviewIntegrityCase
    {
        return StaffReviewIntegrityCase::query()->create([
            'pattern_type' => $data['pattern_type'],
            'pattern_key' => $data['pattern_key'],
            'subject_user_id' => $data['subject_user_id'] ?? null,
            'pattern_data' => $data['pattern_data'] ?? [],
            'status' => 'open',
            'investigated_by_staff_id' => $staff->id,
        ]);
    }

    public function saveFindings(StaffReviewIntegrityCase $case, User $staff, string $findings, ?array $flaggedReviewIds = null): void
    {
        $case->forceFill([
            'findings' => $findings,
            'investigated_by_staff_id' => $staff->id,
            'flagged_review_ids' => $flaggedReviewIds ?? $case->flagged_review_ids,
            'status' => 'investigating',
        ])->save();

        $this->logger->log($staff, 'staff_review_integrity.findings', StaffReviewIntegrityCase::class, $case->id, []);
    }

    public function bulkFlag(StaffReviewIntegrityCase $case, User $staff, array $reviewIds): void
    {
        $merged = array_values(array_unique(array_merge($case->flagged_review_ids ?? [], $reviewIds)));
        $case->forceFill([
            'flagged_review_ids' => $merged,
            'status' => 'flagged',
            'investigated_by_staff_id' => $staff->id,
        ])->save();

        $this->logger->log($staff, 'staff_review_integrity.bulk_flag', StaffReviewIntegrityCase::class, $case->id, [
            'review_ids' => $merged,
        ]);
    }

    public function escalate(StaffReviewIntegrityCase $case, User $staff): void
    {
        $case->forceFill([
            'escalated_to_super_admin' => true,
            'status' => 'escalated',
            'investigated_by_staff_id' => $staff->id,
        ])->save();

        $this->logger->log($staff, 'staff_review_integrity.escalated', StaffReviewIntegrityCase::class, $case->id, []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function detectPatterns(): array
    {
        $signals = [];
        $signals = array_merge($signals, $this->detectRatingSpikes());
        $signals = array_merge($signals, $this->detectPolarizedReviewers());
        $signals = array_merge($signals, $this->detectReciprocalReviews());
        $signals = array_merge($signals, $this->detectTimingClusters());

        return $signals;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectRatingSpikes(): array
    {
        $recentStart = now()->subDays(14);
        $priorStart = now()->subDays(44);
        $priorEnd = now()->subDays(14);

        $candidates = Review::query()
            ->where('created_at', '>=', $recentStart)
            ->select('reviewee_id')
            ->groupBy('reviewee_id')
            ->havingRaw('COUNT(*) >= 3')
            ->pluck('reviewee_id');

        $out = [];
        foreach ($candidates as $revieweeId) {
            $recentAvg = (float) Review::query()
                ->where('reviewee_id', $revieweeId)
                ->where('created_at', '>=', $recentStart)
                ->avg('rating');
            $priorAvg = Review::query()
                ->where('reviewee_id', $revieweeId)
                ->whereBetween('created_at', [$priorStart, $priorEnd])
                ->avg('rating');
            if ($priorAvg === null) {
                continue;
            }
            $jump = $recentAvg - (float) $priorAvg;
            if ($jump >= 1.2) {
                $user = User::query()->find($revieweeId);
                $out[] = [
                    'pattern_type' => 'rating_spike',
                    'pattern_key' => 'user:'.$revieweeId,
                    'label' => ($user?->name ?? 'Freelancer').' rating spike',
                    'signal' => sprintf('Average jumped %.1f stars in 14 days (%.1f → %.1f).', $jump, (float) $priorAvg, $recentAvg),
                    'subject_user_id' => (int) $revieweeId,
                    'pattern_data' => ['recent_avg' => $recentAvg, 'prior_avg' => $priorAvg],
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectPolarizedReviewers(): array
    {
        $rows = Review::query()
            ->select('reviewer_id', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN rating IN (1,5) THEN 1 ELSE 0 END) as polarized'))
            ->groupBy('reviewer_id')
            ->havingRaw('total >= 5')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            if ($row->total > 0 && ($row->polarized / $row->total) >= 0.9) {
                $user = User::query()->find($row->reviewer_id);
                $out[] = [
                    'pattern_type' => 'polarized_reviewer',
                    'pattern_key' => 'reviewer:'.$row->reviewer_id,
                    'label' => ($user?->name ?? 'Reviewer').' polarized scores',
                    'signal' => sprintf('%d of %d reviews are only 1★ or 5★.', $row->polarized, $row->total),
                    'subject_user_id' => (int) $row->reviewer_id,
                    'pattern_data' => ['total' => $row->total, 'polarized' => $row->polarized],
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectReciprocalReviews(): array
    {
        $pairs = Review::query()
            ->select('r1.reviewer_id as a', 'r1.reviewee_id as b', DB::raw('COUNT(*) as exchanges'))
            ->from('reviews as r1')
            ->join('reviews as r2', function ($join): void {
                $join->on('r1.reviewer_id', '=', 'r2.reviewee_id')
                    ->on('r1.reviewee_id', '=', 'r2.reviewer_id');
            })
            ->whereColumn('r1.reviewer_id', '<', 'r1.reviewee_id')
            ->groupBy('r1.reviewer_id', 'r1.reviewee_id')
            ->havingRaw('exchanges >= 2')
            ->limit(20)
            ->get();

        $out = [];
        foreach ($pairs as $pair) {
            $a = User::query()->find($pair->a);
            $b = User::query()->find($pair->b);
            $out[] = [
                'pattern_type' => 'reciprocal_reviews',
                'pattern_key' => 'pair:'.$pair->a.':'.$pair->b,
                'label' => ($a?->name ?? 'User').' ↔ '.($b?->name ?? 'User'),
                'signal' => 'Mutual reviews across multiple contracts detected.',
                'subject_user_id' => (int) $pair->a,
                'pattern_data' => ['user_a' => $pair->a, 'user_b' => $pair->b, 'exchanges' => $pair->exchanges],
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectTimingClusters(): array
    {
        $clusters = Review::query()
            ->select('reviewee_id', DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") as minute_bucket'), DB::raw('COUNT(*) as cnt'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('reviewee_id', 'minute_bucket')
            ->havingRaw('cnt >= 4')
            ->orderByDesc('cnt')
            ->limit(15)
            ->get();

        $out = [];
        foreach ($clusters as $cluster) {
            $user = User::query()->find($cluster->reviewee_id);
            $out[] = [
                'pattern_type' => 'timing_cluster',
                'pattern_key' => 'cluster:'.$cluster->reviewee_id.':'.$cluster->minute_bucket,
                'label' => ($user?->name ?? 'Account').' review burst',
                'signal' => sprintf('%d reviews within one minute (%s).', $cluster->cnt, $cluster->minute_bucket),
                'subject_user_id' => (int) $cluster->reviewee_id,
                'pattern_data' => ['minute_bucket' => $cluster->minute_bucket, 'count' => $cluster->cnt],
            ];
        }

        return $out;
    }

    private function caseRow(StaffReviewIntegrityCase $case): array
    {
        return [
            'id' => $case->id,
            'pattern_type' => $case->pattern_type,
            'pattern_label' => config('operations_extended.review_integrity_patterns')[$case->pattern_type] ?? $case->pattern_type,
            'pattern_key' => $case->pattern_key,
            'status' => $case->status,
            'subject' => $case->subject ? ['id' => $case->subject->id, 'name' => $case->subject->name, 'email' => $case->subject->email] : null,
            'findings' => $case->findings,
            'escalated' => $case->escalated_to_super_admin,
            'flagged_count' => count($case->flagged_review_ids ?? []),
            'updated_at' => $case->updated_at?->toIso8601String(),
        ];
    }
}
