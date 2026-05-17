<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PromotionCouponRedemption extends Model
{
    protected $fillable = [
        'promotion_coupon_id',
        'user_id',
        'redeemable_type',
        'redeemable_id',
        'payment_type',
        'transaction_amount_minor',
        'discount_amount_minor',
        'net_amount_minor',
        'ip_address',
        'device_fingerprint',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(PromotionCoupon::class, 'promotion_coupon_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function redeemable(): MorphTo
    {
        return $this->morphTo();
    }
}
