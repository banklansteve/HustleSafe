<?php

namespace App\Services\Operations;

use App\Enums\ReviewStatus;
use App\Models\ModerationAppeal;
use App\Models\ModerationCase;
use App\Models\Review;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffReviewModerationService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function listing(Request $request): LengthAwarePaginator
    {
        $queue = (string) $request->input('queue', 'pending');
        $q = trim((string) $request->input('q', ''));

        $query = Review::query()
            ->with(['quest:id,title,reference_code', 'reviewer:id,name,email', 'reviewee:id,name,email']);

        $query = match ($queue) {
            'published' => $query->where('status', ReviewStatus::Published),
            'revision' => $query->where('status', ReviewStatus::RevisionRequested),
            'removed' => $query->where('status', ReviewStatus::Removed),
            'flagged' => $query->whereHas('moderationCases', fn (Builder $sub) => $sub->whereIn('status', ['open', 'in_review'])),
            'appeals' => $query->whereHas('moderationCases.appeals', fn (Builder $sub) => $sub->where('status', 'open')),
            default => $query->whereIn('status', [ReviewStatus::PendingReview, ReviewStatus::Draft]),
        };

        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('comment', 'like', "%{$q}%")
                    ->orWhereHas('reviewer', fn (Builder $u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('reviewee', fn (Builder $u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
            });
        }

        return $query->latest()->paginate(min(100, max(25, $request->integer('per_page', 50))))
            ->withQueryString()
            ->through(fn (Review $review) => $this->row($review));
    }

    public function queues(): array
    {
        return [
            ['key' => 'pending', 'label' => 'Pending review', 'hint' => 'Awaiting staff decision'],
            ['key' => 'flagged', 'label' => 'Flagged', 'hint' => 'Open moderation cases'],
            ['key' => 'appeals', 'label' => 'Appeals', 'hint' => 'User appeals to review'],
            ['key' => 'revision', 'label' => 'Revision requested', 'hint' => 'Waiting on reviewer'],
            ['key' => 'published', 'label' => 'Published', 'hint' => 'Live reviews'],
            ['key' => 'removed', 'label' => 'Removed', 'hint' => 'Removed by staff'],
        ];
    }

    public function detail(Review $review): array
    {
        $review->load(['quest.client:id,name,email', 'quest.freelancer:id,name,email', 'reviewer:id,name,email', 'reviewee:id,name,email', 'attachments']);

        $case = ModerationCase::query()
            ->where('moderatable_type', Review::class)
            ->where('moderatable_id', $review->id)
            ->with(['appeals' => fn ($q) => $q->latest()])
            ->latest()
            ->first();

        return [
            'review' => [
                ...$this->row($review),
                'comment_full' => $review->comment,
                'tags' => $review->tags ?? [],
                'attachments' => $review->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'url' => $a->url ?? null,
                    'label' => $a->original_name ?? 'Attachment',
                ]),
            ],
            'moderation_case' => $case ? [
                'id' => $case->id,
                'status' => $case->status,
                'severity' => $case->severity,
                'appeals' => $case->appeals->map(fn (ModerationAppeal $appeal) => [
                    'id' => $appeal->id,
                    'status' => $appeal->status,
                    'statement' => $appeal->statement,
                    'created_at' => $appeal->created_at?->toIso8601String(),
                ]),
            ] : null,
        ];
    }

    public function approveWithEdits(Review $review, User $staff, array $data, Request $request): Review
    {
        return DB::transaction(function () use ($review, $staff, $data, $request): Review {
            $review->fill(array_filter([
                'title' => $data['title'] ?? null,
                'comment' => $data['comment'] ?? null,
                'status' => ReviewStatus::Published,
            ], fn ($v) => $v !== null))->save();

            $this->closeModerationCase($review, $staff, 'approve', $data['reason'] ?? 'Approved by staff.');
            $this->logger->log($staff, 'operations.review.approved', Review::class, $review->id, [], $request);

            return $review->fresh();
        });
    }

    public function remove(Review $review, User $staff, array $data, Request $request): Review
    {
        return DB::transaction(function () use ($review, $staff, $data, $request): Review {
            $review->forceFill(['status' => ReviewStatus::Removed])->save();
            $this->closeModerationCase($review, $staff, 'remove', $data['reason']);
            $this->logger->log($staff, 'operations.review.removed', Review::class, $review->id, ['reason' => $data['reason']], $request);

            return $review->fresh();
        });
    }

    public function requestRevision(Review $review, User $staff, array $data, Request $request): Review
    {
        return DB::transaction(function () use ($review, $staff, $data, $request): Review {
            $review->forceFill(['status' => ReviewStatus::RevisionRequested])->save();
            $this->ensureModerationCase($review, $staff, 'revision_requested', $data['reason']);
            $this->logger->log($staff, 'operations.review.revision_requested', Review::class, $review->id, ['reason' => $data['reason']], $request);

            return $review->fresh();
        });
    }

    public function flag(Review $review, User $staff, array $data, Request $request): void
    {
        $this->ensureModerationCase($review, $staff, 'flagged', $data['description'], $data['priority'] ?? 'medium');
        $this->logger->log($staff, 'operations.review.flagged', Review::class, $review->id, $data, $request);
    }

    public function resolveAppeal(ModerationAppeal $appeal, User $staff, array $data, Request $request): void
    {
        DB::transaction(function () use ($appeal, $staff, $data, $request): void {
            $appeal->loadMissing('case.moderatable');
            $appeal->forceFill([
                'status' => $data['outcome'] === 'uphold' ? 'upheld' : 'overturned',
                'review_note' => $data['note'],
                'reviewed_by' => $staff->id,
                'reviewed_at' => now(),
            ])->save();

            if ($data['outcome'] === 'overturn' && $appeal->case?->moderatable instanceof Review) {
                $appeal->case->moderatable->forceFill(['status' => ReviewStatus::Published])->save();
            }

            $appeal->case?->forceFill([
                'status' => 'decided',
                'decided_at' => now(),
                'decision' => $data['outcome'],
                'decision_note' => $data['note'],
            ])->save();

            $this->logger->log($staff, 'operations.review.appeal_resolved', ModerationAppeal::class, $appeal->id, $data, $request);
        });
    }

    private function row(Review $review): array
    {
        return [
            'id' => $review->id,
            'status' => $review->status?->value ?? (string) $review->status,
            'rating' => $review->rating,
            'title' => $review->title,
            'comment' => str((string) $review->comment)->limit(160)->toString(),
            'quest' => $review->quest?->title,
            'quest_id' => $review->quest_id,
            'reviewer' => $review->reviewer?->name,
            'reviewee' => $review->reviewee?->name,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    private function ensureModerationCase(Review $review, User $staff, string $decision, string $reason, string $severity = 'medium'): ModerationCase
    {
        $case = ModerationCase::query()->firstOrCreate(
            [
                'moderatable_type' => Review::class,
                'moderatable_id' => $review->id,
                'status' => 'open',
            ],
            [
                'subject_user_id' => $review->reviewee_id,
                'assigned_admin_id' => $staff->id,
                'content_type' => 'review',
                'queue' => 'reviews',
                'severity' => $severity,
                'title' => $review->title ?: 'Review #'.$review->id,
                'excerpt' => str($review->comment)->limit(200)->toString(),
                'snapshot' => $review->only(['title', 'comment', 'rating', 'status']),
            ],
        );

        $case->forceFill([
            'assigned_admin_id' => $staff->id,
            'decision_note' => $reason,
        ])->save();

        return $case;
    }

    private function closeModerationCase(Review $review, User $staff, string $decision, string $note): void
    {
        ModerationCase::query()
            ->where('moderatable_type', Review::class)
            ->where('moderatable_id', $review->id)
            ->whereIn('status', ['open', 'in_review'])
            ->update([
                'status' => 'decided',
                'decision' => $decision,
                'decision_note' => $note,
                'decided_at' => now(),
                'assigned_admin_id' => $staff->id,
            ]);
    }
}
