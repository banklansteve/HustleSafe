<?php

namespace App\Observers;

use App\Models\UserVerification;
use App\Services\Verification\VerificationEngineService;
use App\Services\TrustScoreOrchestrator;
use App\Support\TrustRisk\UserRiskScoreDispatcher;

class UserVerificationObserver
{
    public function updated(UserVerification $verification): void
    {
        if ($verification->wasChanged('status')) {
            $verification->loadMissing('user');
            if ($verification->user !== null) {
                app(VerificationEngineService::class)->recalculate($verification->user->fresh(), null, 'Verification status changed.');
                app(TrustScoreOrchestrator::class)->recalculate($verification->user->fresh());
                UserRiskScoreDispatcher::dispatch((int) $verification->user_id);
            }
        }
    }
}
