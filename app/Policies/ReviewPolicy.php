<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        if ($review->reviewer_id !== $user->id) {
            return false;
        }

        return $review->isEditable();
    }
}
