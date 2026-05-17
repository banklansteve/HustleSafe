<?php

namespace App\Services\Kyc;

use App\Models\KycSetting;
use App\Models\User;

class KycTierGateService
{
    public function tier(User $user): int
    {
        $tier = max((int) ($user->kyc_tier ?? 0), (int) ($user->verification_tier ?? 0));

        if ($tier < 1 && $user->hasVerifiedEmail() && $user->phone_verified_at !== null) {
            return 1;
        }

        return $tier;
    }

    public function requiredTier(string $feature): int
    {
        $gates = KycSetting::value('feature_gates', config('kyc.feature_gates', []));

        return (int) ($gates[$feature] ?? config("kyc.feature_gates.{$feature}", 0));
    }

    public function allows(User $user, string $feature): bool
    {
        return $this->tier($user) >= $this->requiredTier($feature);
    }

    public function clientQuestLimitMinor(User $user): int
    {
        $limits = KycSetting::value('limits', config('kyc.limits', []));
        $tier = $this->tier($user);

        if ($tier >= 4) {
            return PHP_INT_MAX;
        }
        if ($tier >= 2) {
            return (int) ($limits['tier_2_client_quest_minor'] ?? config('kyc.limits.tier_2_client_quest_minor'));
        }
        if ($tier >= 1) {
            return (int) ($limits['tier_1_client_quest_minor'] ?? config('kyc.limits.tier_1_client_quest_minor'));
        }

        return 0;
    }
}
