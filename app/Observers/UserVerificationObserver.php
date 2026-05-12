<?php

namespace App\Observers;

use App\Models\UserVerification;
use App\Services\TrustScoreOrchestrator;

class UserVerificationObserver
{
    public function updated(UserVerification $verification): void
    {
        if ($verification->wasChanged('status')) {
            $verification->loadMissing('user');
            if ($verification->user !== null) {
                app(TrustScoreOrchestrator::class)->recalculate($verification->user->fresh());
            }
        }
    }
}
