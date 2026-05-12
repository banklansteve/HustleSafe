<?php

namespace App\Services;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\User;
use App\Models\UserVerification;

/**
 * Maps KYC / document verification outcomes into 0–1 inputs for trust scoring.
 */
class VerificationTrustComponentService
{
    public function emailVerifiedNorm(User $user): float
    {
        return $user->hasVerifiedEmail() ? 1.0 : 0.0;
    }

    public function identityVerifiedNorm(User $user): float
    {
        return $this->hasApprovedCategory($user, UserVerificationCategory::Identity) ? 1.0 : 0.0;
    }

    public function addressVerifiedNorm(User $user): float
    {
        return $this->hasApprovedCategory($user, UserVerificationCategory::Address) ? 1.0 : 0.0;
    }

    /**
     * Share of public credentials with an approved qualification verification.
     */
    public function qualificationsVerifiedNorm(User $user): float
    {
        $neutral = (float) config('scoring.freelancer.neutral_qualifications_norm', 0.55);

        $publicCount = $user->freelancerCredentials()->where('is_public', true)->count();
        if ($publicCount === 0) {
            return $neutral;
        }

        $approvedCredentialIds = UserVerification::query()
            ->where('user_id', $user->id)
            ->where('category', UserVerificationCategory::Qualification)
            ->where('status', UserVerificationStatus::Approved)
            ->whereNotNull('freelancer_credential_id')
            ->pluck('freelancer_credential_id')
            ->unique()
            ->count();

        return min(1.0, $approvedCredentialIds / max(1, $publicCount));
    }

    /**
     * CAC / business registration signal (freelancers only).
     */
    public function cacVerifiedNorm(User $user): float
    {
        $neutral = (float) config('scoring.freelancer.neutral_cac_norm', 0.5);
        $profile = $user->freelancerBusinessProfile;
        if ($profile === null || trim((string) $profile->cac_registration_number) === '') {
            return $neutral;
        }

        return match ($profile->cac_verification_status) {
            'verified' => 1.0,
            'rejected' => 0.15,
            'pending', 'manual_review' => 0.55,
            default => 0.45,
        };
    }

    protected function hasApprovedCategory(User $user, UserVerificationCategory $category): bool
    {
        return UserVerification::query()
            ->where('user_id', $user->id)
            ->where('category', $category)
            ->where('status', UserVerificationStatus::Approved)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
