<?php

namespace App\Services\Kyc;

use App\Models\User;
use App\Services\Verification\VerificationEngineService;

class KycTierGateService
{
    public function __construct(private readonly VerificationEngineService $engine) {}

    public function tier(User $user): int
    {
        return $this->engine->effectiveLevel($user);
    }

    public function requiredTier(string $feature): int
    {
        return match ($feature) {
            'post_quest', 'submit_proposal' => 1,
            default => (int) config("kyc.feature_gates.{$feature}", 0),
        };
    }

    public function allows(User $user, string $feature): bool
    {
        return $this->tier($user) >= $this->requiredTier($feature);
    }

    public function clientQuestLimitMinor(User $user): int
    {
        return $this->engine->clientPostingLimitMinor($user);
    }
}
