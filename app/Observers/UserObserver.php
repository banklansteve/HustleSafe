<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Geocoding\GeocodeUserAddress;

class UserObserver
{
    public function created(User $user): void
    {
        app(GeocodeUserAddress::class)($user);
        app(\App\Services\Payments\WalletService::class)->ensureWallet($user);
        app(\App\Services\Onboarding\OnboardingQualityControlService::class)->ensureReviewFor($user);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged(['address_line', 'city', 'state_id', 'local_government_id'])) {
            app(GeocodeUserAddress::class)($user);
        }

        if ($user->onboardingQualityReview()->exists()) {
            app(\App\Services\Onboarding\OnboardingQualityControlService::class)->syncEvaluation($user);
        }
    }
}
