<?php

namespace App\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FreelancerReviewsDirectoryController extends Controller
{
    public function __invoke(Request $request, string $slug): Response
    {
        $profile = $this->resolveFreelancer($slug);

        $q = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'latest');
        $rating = $request->query('rating');

        $query = Review::query()
            ->where('reviewee_id', $profile->id)
            ->where('status', ReviewStatus::Published)
            ->with(['quest:id,title', 'reviewer:id,first_name,name', 'attachments']);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('comment', 'like', '%'.$q.'%');
            });
        }

        if ($rating !== null && $rating !== '' && is_numeric($rating)) {
            $query->where('rating', (int) $rating);
        }

        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'rating_high' => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'rating_low' => $query->orderBy('rating')->orderByDesc('created_at'),
            default => $query->latest('created_at'),
        };

        $reviews = $query->paginate(12)->withQueryString();

        return Inertia::render('Profile/PublicReviews', [
            'profile' => [
                'slug' => $profile->slug,
                'name' => $profile->first_name ?: $profile->name,
                'avatar_url' => $profile->avatar_url,
            ],
            'reviews' => $reviews->through(fn (Review $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'title' => $r->title,
                'comment' => $r->comment,
                'created_at' => $r->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'quest_title' => $r->quest?->title,
                'reviewer_label' => $r->reviewer?->first_name ?: $r->reviewer?->name,
                'attachments' => $r->attachments->map(fn ($a) => [
                    'url' => $a->url(),
                    'original_name' => $a->original_name,
                ])->all(),
            ]),
            'filters' => [
                'q' => $q,
                'sort' => $sort,
                'rating' => $rating,
            ],
        ]);
    }

    protected function resolveFreelancer(string $slug): User
    {
        $user = User::query()
            ->where('slug', $slug)
            ->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))
            ->first();

        if ($user === null) {
            throw new NotFoundHttpException;
        }

        return $user;
    }
}
