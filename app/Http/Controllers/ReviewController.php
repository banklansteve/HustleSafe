<?php

namespace App\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Enums\ReviewType;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Models\ActivityLog;
use App\Models\Quest;
use App\Models\Review;
use App\Services\ReviewEligibilityService;
use App\Services\TrustScoreOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewEligibilityService $eligibility,
        protected TrustScoreOrchestrator $trustScores,
    ) {}

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $user = $request->user();
        $quest = Quest::query()->findOrFail($request->integer('quest_id'));

        if (! $this->eligibility->canReview($user, $quest)) {
            throw ValidationException::withMessages([
                'quest_id' => __('You cannot leave a review for this quest yet.'),
            ]);
        }

        if (Review::query()->where('quest_id', $quest->id)->where('reviewer_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'quest_id' => __('You already submitted feedback for this quest.'),
            ]);
        }

        $reviewee = $this->eligibility->expectedReviewee($user, $quest);
        if ($reviewee === null || $reviewee->id !== $request->integer('reviewee_id')) {
            throw ValidationException::withMessages([
                'reviewee_id' => __('Invalid review recipient for this quest.'),
            ]);
        }

        $type = $this->eligibility->resolveReviewType($quest);

        $rating = $request->input('rating');
        if ($type === ReviewType::Full && ($rating === null || $rating === '')) {
            throw ValidationException::withMessages([
                'rating' => __('Please choose a star rating for completed quests.'),
            ]);
        }

        if ($type === ReviewType::Partial && $rating !== null && $rating !== '') {
            throw ValidationException::withMessages([
                'rating' => __('Star ratings are disabled when a quest ends without a clean completion.'),
            ]);
        }

        $party = $user->id === $quest->client_id ? 'client' : 'freelancer';

        $hours = (int) config('scoring.reviews.edit_window_hours', 72);

        $review = DB::transaction(function () use ($request, $quest, $user, $reviewee, $type, $rating, $party, $hours) {
            return Review::query()->create([
                'quest_id' => $quest->id,
                'reviewer_id' => $user->id,
                'reviewee_id' => $reviewee->id,
                'reviewer_party' => $party,
                'review_type' => $type,
                'rating' => $type === ReviewType::Full ? (int) $rating : null,
                'title' => $request->input('title'),
                'comment' => $request->input('comment'),
                'tags' => $request->input('tags'),
                'status' => ReviewStatus::Published,
                'edit_window_ends_at' => now()->addHours($hours),
            ]);
        });

        $this->trustScores->recalculate($reviewee->fresh());

        ActivityLog::query()->create([
            'subject_user_id' => $reviewee->id,
            'actor_id' => $user->id,
            'type' => 'review_received',
            'title' => __('Feedback received'),
            'body' => __('Regarding quest: :title', ['title' => $quest->title]),
            'meta' => ['quest_id' => $quest->id, 'review_id' => $review->id],
            'created_at' => now(),
        ]);

        return back()->with('success', __('Thanks — your feedback strengthens trust on HustleSafe.'));
    }

    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $this->authorize('update', $review);

        if ($review->review_type === ReviewType::Partial && $request->filled('rating')) {
            throw ValidationException::withMessages([
                'rating' => __('Ratings cannot be added to this type of feedback.'),
            ]);
        }

        $review->fill([
            'title' => $request->input('title', $review->title),
            'comment' => $request->input('comment', $review->comment),
            'tags' => $request->input('tags', $review->tags),
        ]);

        if ($review->review_type === ReviewType::Full && $request->has('rating')) {
            $review->rating = (int) $request->input('rating');
        }

        $review->save();

        $this->trustScores->recalculate($review->reviewee->fresh());

        return back()->with('success', __('Review updated.'));
    }
}
