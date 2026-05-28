<?php

namespace App\Services\ReviewModeration;

use App\Enums\ReviewAuthenticityFlag;
use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\ReviewModerationCluster;
use App\Models\User;
use App\Services\Operations\StaffReviewModerationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReviewModerationAdminService
{
    public function __construct(
        private readonly StaffReviewModerationService $staffModeration,
        private readonly ReviewManipulationReportService $manipulation,
        private readonly ReviewAmendmentService $amendments,
        private readonly ReviewModerationActionLogger $logger,
        private readonly ReviewModerationPipelineService $pipeline,
    ) {}

    public function queues(): array
    {
        $base = $this->staffModeration->queues();

        return array_merge([
            ['key' => 'authenticity', 'label' => 'Authenticity flags', 'hint' => 'Suspicious & high-risk automated signals'],
            ['key' => 'clusters', 'label' => 'Pattern clusters', 'hint' => 'Velocity, reciprocal, and IP groups'],
            ['key' => 'amendments', 'label' => 'Amendment pending', 'hint' => 'Awaiting reviewer updates'],
            ['key' => 'manipulation', 'label' => 'Manipulation dashboard', 'hint' => 'Nightly concentration & client pattern reports'],
        ], $base);
    }

    public function listing(Request $request): LengthAwarePaginator
    {
        $queue = (string) $request->input('queue', 'authenticity');

        if ($queue === 'manipulation') {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 1);
        }

        if ($queue === 'clusters') {
            return $this->clusterListing($request);
        }

        if (in_array($queue, ['authenticity', 'amendments'], true)) {
            return $this->authenticityListing($request, $queue);
        }

        return $this->staffModeration->listing($request);
    }

    public function manipulationDashboard(): array
    {
        return [
            'freelancer_concentration' => $this->manipulation->latest('freelancer_concentration'),
            'client_pattern' => $this->manipulation->latest('client_pattern'),
            'generated_hint' => __('Refreshed nightly by review-manipulation:refresh'),
        ];
    }

    public function detail(Review $review): array
    {
        $base = $this->staffModeration->detail($review);
        $review->load(['authenticitySignals', 'moderationCluster.reviews.reviewer:id,name,email', 'amendmentRequests.issuer:id,name']);

        $clusterReviews = [];
        if ($review->moderation_cluster_id) {
            $clusterReviews = Review::query()
                ->where('moderation_cluster_id', $review->moderation_cluster_id)
                ->with(['reviewer:id,name,email', 'reviewee:id,name,email', 'quest:id,title'])
                ->get()
                ->map(fn (Review $r) => $this->staffModeration->row($r))
                ->all();
        }

        $reciprocalId = $review->authenticitySignals
            ->firstWhere('signal_type', 'reciprocal_pair')
            ?->metadata['counterpart_review_id'] ?? null;

        $counterpart = $reciprocalId
            ? Review::query()->with(['reviewer:id,name,email', 'reviewee:id,name,email', 'quest:id,title'])->find($reciprocalId)
            : null;

        $base['review'] = array_merge($base['review'], [
            'authenticity_flag' => $review->authenticity_flag?->value ?? (string) $review->authenticity_flag,
            'quality_score' => $review->quality_score,
            'is_brief' => (bool) $review->is_brief,
            'sentiment_score' => $review->sentiment_score,
            'reviewer_subnet' => $review->reviewer_subnet,
            'subnet_collision' => $review->authenticitySignals->contains('signal_type', 'ip_cluster'),
            'signals' => $review->authenticitySignals->map(fn ($s) => [
                'type' => $s->signal_type,
                'label' => $s->label,
                'metadata' => $s->metadata,
                'confidence' => $s->confidence,
            ])->values(),
            'action_logs' => $review->id
                ? \App\Models\ReviewModerationActionLog::query()->where('review_id', $review->id)->latest()->limit(30)->get()->map(fn ($l) => [
                    'action' => $l->action,
                    'note' => $l->note,
                    'payload' => $l->payload,
                    'occurred_at' => $l->occurred_at?->toIso8601String(),
                ])
                : [],
        ]);

        $base['cluster_reviews'] = $clusterReviews;
        $base['reciprocal_counterpart'] = $counterpart ? $this->staffModeration->row($counterpart) : null;
        $base['open_amendment'] = $review->amendmentRequests->firstWhere('status', 'open');

        return $base;
    }

    public function issueAmendment(Review $review, User $staff, array $data, Request $request): void
    {
        $this->amendments->issue(
            $review,
            $staff,
            $data['instructions'],
            $data['required_changes'] ?? [],
        );
        $this->logger->log($review, $staff, 'staff_amendment_issued', $data['instructions'], $data);
    }

    public function freelancerBreakdown(int $freelancerId): array
    {
        $report = $this->manipulation->latest('freelancer_concentration');
        $row = collect($report['rows'])->firstWhere('freelancer_id', $freelancerId);
        if ($row === null) {
            return ['reviews' => []];
        }

        $reviews = Review::query()
            ->whereIn('id', $row['review_ids'] ?? [])
            ->with(['reviewer:id,name,email', 'quest:id,title'])
            ->get()
            ->map(fn (Review $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'reviewer' => $r->reviewer?->only(['id', 'name', 'email']),
                'quest' => $r->quest?->title,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return ['row' => $row, 'reviews' => $reviews];
    }

    private function authenticityListing(Request $request, string $queue): LengthAwarePaginator
    {
        $q = trim((string) $request->input('q', ''));

        $query = Review::query()
            ->with(['quest:id,title', 'reviewer:id,name,email', 'reviewee:id,name,email', 'moderationCluster']);

        if ($queue === 'amendments') {
            $query->where('status', ReviewStatus::AmendmentPending);
        } else {
            $query->where('status', ReviewStatus::PendingReview)
                ->whereIn('authenticity_flag', [
                    ReviewAuthenticityFlag::Suspicious->value,
                    ReviewAuthenticityFlag::HighRisk->value,
                ]);
        }

        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('comment', 'like', "%{$q}%")
                    ->orWhereHas('reviewer', fn (Builder $u) => $u->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('reviewee', fn (Builder $u) => $u->where('name', 'like', "%{$q}%"));
            });
        }

        return $query->latest()->paginate(min(100, max(25, $request->integer('per_page', 50))))
            ->withQueryString()
            ->through(fn (Review $review) => $this->staffModeration->row($review));
    }

    private function clusterListing(Request $request): LengthAwarePaginator
    {
        $query = ReviewModerationCluster::query()
            ->with(['primaryReviewee:id,name,email'])
            ->where('status', 'open')
            ->latest();

        return $query->paginate(min(50, max(10, $request->integer('per_page', 25))))
            ->withQueryString()
            ->through(fn (ReviewModerationCluster $cluster) => [
                'id' => 'cluster-'.$cluster->id,
                'cluster_id' => $cluster->id,
                'cluster_type' => $cluster->cluster_type,
                'status' => $cluster->status,
                'reviewee' => $cluster->primaryReviewee?->name,
                'metadata' => $cluster->metadata,
                'created_at' => $cluster->created_at?->toIso8601String(),
                'is_cluster' => true,
            ]);
    }

    public function clusterDetail(ReviewModerationCluster $cluster): array
    {
        $reviews = Review::query()
            ->where('moderation_cluster_id', $cluster->id)
            ->with(['reviewer:id,name,email', 'reviewee:id,name,email', 'quest:id,title', 'authenticitySignals'])
            ->get();

        return [
            'cluster' => [
                'id' => $cluster->id,
                'cluster_type' => $cluster->cluster_type,
                'status' => $cluster->status,
                'metadata' => $cluster->metadata,
            ],
            'reviews' => $reviews->map(fn (Review $r) => array_merge($this->staffModeration->row($r), [
                'signals' => $r->authenticitySignals->pluck('signal_type'),
            ])),
        ];
    }
}
