<?php

namespace App\Services\Promotions;

use App\Models\PromotionCoupon;
use App\Models\PromotionCouponFraudFlag;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponEngineService
{
    public function previewDiscount(PromotionCoupon $coupon, User $user, int $amountMinor, string $paymentType, ?string $ip = null, ?string $device = null): array
    {
        $this->assertUsable($coupon, $user, $amountMinor, $paymentType);
        $this->flagFraudIfNeeded($coupon, $user, $ip, $device);

        $discount = $coupon->discount_type === 'percent'
            ? (int) round($amountMinor * (((int) $coupon->discount_percent) / 100))
            : (int) $coupon->discount_value_minor;

        if ($coupon->max_discount_minor !== null) {
            $discount = min($discount, (int) $coupon->max_discount_minor);
        }

        $discount = min($discount, $amountMinor);

        return [
            'discount_minor' => $discount,
            'net_minor' => max(0, $amountMinor - $discount),
        ];
    }

    public function assertUsable(PromotionCoupon $coupon, User $user, int $amountMinor, string $paymentType): void
    {
        if ($coupon->status !== 'active') {
            throw ValidationException::withMessages(['coupon' => 'This coupon is not active.']);
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is scheduled for later.']);
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw ValidationException::withMessages(['coupon' => 'This coupon has expired.']);
        }
        if ($amountMinor < (int) $coupon->minimum_transaction_minor) {
            throw ValidationException::withMessages(['coupon' => 'This transaction is below the coupon minimum.']);
        }
        if ($coupon->applies_to !== 'all' && $coupon->applies_to !== $paymentType) {
            throw ValidationException::withMessages(['coupon' => 'This coupon does not apply to this payment type.']);
        }
        if ($coupon->usage_limit_total !== null && $coupon->usage_count >= $coupon->usage_limit_total) {
            throw ValidationException::withMessages(['coupon' => 'This coupon has reached its usage limit.']);
        }
        if ($coupon->usage_limit_per_user !== null && $coupon->redemptions()->where('user_id', $user->id)->count() >= $coupon->usage_limit_per_user) {
            throw ValidationException::withMessages(['coupon' => 'You have already used this coupon the maximum number of times.']);
        }
        if ($coupon->eligibility === 'new_users' && $user->created_at?->lt(now()->subDays(30))) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is for new users only.']);
        }
        if ($coupon->eligibility === 'clients' && $user->role?->slug !== 'client') {
            throw ValidationException::withMessages(['coupon' => 'This coupon is for clients only.']);
        }
        if ($coupon->eligibility === 'freelancers' && $user->role?->slug !== 'freelancer') {
            throw ValidationException::withMessages(['coupon' => 'This coupon is for freelancers only.']);
        }
        if ($coupon->eligibility === 'specific_users' && ! in_array($user->id, $coupon->eligible_user_ids ?? [], true)) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is not assigned to your account.']);
        }
    }

    private function flagFraudIfNeeded(PromotionCoupon $coupon, User $user, ?string $ip, ?string $device): void
    {
        $recentVelocity = $coupon->redemptions()->where('created_at', '>=', now()->subMinute())->count();
        if ($recentVelocity >= 10) {
            PromotionCouponFraudFlag::query()->firstOrCreate([
                'promotion_coupon_id' => $coupon->id,
                'reason' => 'unusual_velocity',
                'status' => 'open',
            ], [
                'user_id' => $user->id,
                'evidence' => ['redemptions_last_minute' => $recentVelocity],
            ]);
        }

        if ($coupon->eligibility === 'new_users' && ($ip || $device)) {
            $sameSource = $coupon->redemptions()
                ->whereHas('user', fn ($q) => $q->where('id', '<>', $user->id))
                ->where(fn ($q) => $q
                    ->when($ip, fn ($qq) => $qq->orWhere('ip_address', $ip))
                    ->when($device, fn ($qq) => $qq->orWhere('device_fingerprint', $device)))
                ->exists();

            if ($sameSource) {
                PromotionCouponFraudFlag::query()->create([
                    'promotion_coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'reason' => 'new_user_multi_account_source',
                    'status' => 'open',
                    'evidence' => ['ip' => $ip, 'device' => $device],
                ]);
            }
        }
    }
}
