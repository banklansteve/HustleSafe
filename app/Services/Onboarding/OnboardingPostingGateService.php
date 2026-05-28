<?php

namespace App\Services\Onboarding;

use App\Enums\OnboardingQualityReviewStatus;
use App\Models\OnboardingQualityReview;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OnboardingPostingGateService
{
    public function blocksPosting(User $user): bool
    {
        if (in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return false;
        }

        $review = OnboardingQualityReview::query()->where('user_id', $user->id)->first();
        if ($review === null) {
            return false;
        }

        return $review->blocksPosting();
    }

    public function blockReason(User $user): ?string
    {
        $review = OnboardingQualityReview::query()->where('user_id', $user->id)->first();
        if ($review === null || ! $review->blocksPosting()) {
            return null;
        }

        return match ($review->status) {
            OnboardingQualityReviewStatus::Escalated => __('Your account is under Trust & Safety review. You cannot post quests or proposals until the team resolves this.'),
            OnboardingQualityReviewStatus::SuspendedPendingReview => __('Your account is suspended pending onboarding review. You cannot post quests or proposals until the review is complete.'),
            default => __('Your account is restricted from posting until onboarding review is complete.'),
        };
    }

    public function assertCanPost(User $user, string $field = 'account'): void
    {
        if (! $this->blocksPosting($user)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $this->blockReason($user) ?? __('Posting is temporarily restricted.'),
        ]);
    }
}
