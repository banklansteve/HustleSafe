<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Inertia\Inertia;
use Inertia\Response;

class OperationsReviewsController extends Controller
{
    public function index(): Response
    {
        $reviews = Review::query()
            ->with(['quest:id,title,reference_code', 'reviewer:id,name,email', 'reviewee:id,name,email'])
            ->latest()
            ->paginate(20)
            ->through(fn (Review $review) => [
                'id' => $review->id,
                'status' => $review->status?->value ?? (string) $review->status,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => str($review->comment ?: 'No review body.')->limit(180)->toString(),
                'quest' => $review->quest?->title,
                'reviewer' => $review->reviewer?->name,
                'reviewee' => $review->reviewee?->name,
                'created_at' => $review->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Operations/Reviews/Index', [
            'reviews' => $reviews,
        ]);
    }
}
