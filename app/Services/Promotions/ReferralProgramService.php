<?php

namespace App\Services\Promotions;

use App\Models\ReferralAbuseFlag;
use App\Models\ReferralReward;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\UserReferral;

class ReferralProgramService
{
    public function recordSignup(User $referred, ?string $code, ?string $ip = null, ?string $device = null): ?UserReferral
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '' || $referred->referred_by_user_id !== null) {
            return null;
        }

        $referrer = User::query()->where('referral_code', $code)->first();
        if (! $referrer || $referrer->id === $referred->id || $referrer->referral_program_blocked_at !== null) {
            return null;
        }

        $referred->forceFill(['referred_by_user_id' => $referrer->id])->save();

        $referral = UserReferral::query()->create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $referred->id,
            'referral_code' => $code,
            'status' => 'signed_up',
            'ip_address' => $ip,
            'device_fingerprint' => $device,
        ]);

        $this->flagSignupAbuse($referral);

        return $referral;
    }

    public function qualify(User $referred, string $event): ?ReferralReward
    {
        $referral = UserReferral::query()->where('referred_user_id', $referred->id)->whereNull('qualified_at')->with('referrer', 'referred.role')->first();
        if (! $referral || $referral->referrer?->referral_program_blocked_at !== null) {
            return null;
        }

        $config = PromotionSetting::value('referral_program', []);
        $amount = $referral->referred?->role?->slug === 'client'
            ? (int) ($config['client_reward_minor'] ?? 0)
            : (int) ($config['freelancer_reward_minor'] ?? 0);

        $referral->update(['status' => 'qualified', 'qualifying_event' => $event, 'qualified_at' => now()]);

        return ReferralReward::query()->create([
            'user_referral_id' => $referral->id,
            'user_id' => $referral->referrer_user_id,
            'reward_type' => $config['reward_type'] ?? 'wallet_credit',
            'amount_minor' => $amount,
            'status' => 'pending',
            'expires_at' => now()->addDays((int) ($config['reward_expiry_days'] ?? 90)),
            'metadata' => ['event' => $event],
        ]);
    }

    private function flagSignupAbuse(UserReferral $referral): void
    {
        $recentCount = UserReferral::query()
            ->where('referrer_user_id', $referral->referrer_user_id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($recentCount > 20) {
            ReferralAbuseFlag::query()->firstOrCreate([
                'referrer_user_id' => $referral->referrer_user_id,
                'reason' => 'high_referral_velocity',
                'status' => 'open',
            ], [
                'user_referral_id' => $referral->id,
                'evidence' => ['referrals_last_7_days' => $recentCount],
            ]);
        }

        $sharedSource = UserReferral::query()
            ->where('id', '<>', $referral->id)
            ->where('referrer_user_id', $referral->referrer_user_id)
            ->where(fn ($q) => $q
                ->when($referral->ip_address, fn ($qq) => $qq->orWhere('ip_address', $referral->ip_address))
                ->when($referral->device_fingerprint, fn ($qq) => $qq->orWhere('device_fingerprint', $referral->device_fingerprint)))
            ->exists();

        if ($sharedSource) {
            ReferralAbuseFlag::query()->create([
                'user_referral_id' => $referral->id,
                'referrer_user_id' => $referral->referrer_user_id,
                'reason' => 'shared_ip_or_device',
                'status' => 'open',
                'evidence' => ['ip' => $referral->ip_address, 'device' => $referral->device_fingerprint],
            ]);
        }
    }
}
