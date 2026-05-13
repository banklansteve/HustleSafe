<?php

namespace App\Policies;

use App\Enums\PortfolioStatus;
use App\Models\Portfolio;
use App\Models\User;

class PortfolioPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Portfolio $portfolio): bool
    {
        if ($user !== null && $user->id === $portfolio->user_id) {
            return true;
        }

        if ($portfolio->admin_hidden) {
            return false;
        }

        return $portfolio->status === PortfolioStatus::Published;
    }

    public function create(User $user): bool
    {
        return $user->role?->slug === 'freelancer';
    }

    public function update(User $user, Portfolio $portfolio): bool
    {
        return $user->role?->slug === 'freelancer' && $user->id === $portfolio->user_id;
    }

    public function delete(User $user, Portfolio $portfolio): bool
    {
        return $this->update($user, $portfolio);
    }

    /**
     * Save to favourites (public published work only, not own).
     */
    public function favorite(User $user, Portfolio $portfolio): bool
    {
        if ($user->id === $portfolio->user_id) {
            return false;
        }

        return $portfolio->isVisibleToPublic();
    }
}
