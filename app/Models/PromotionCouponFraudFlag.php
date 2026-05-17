<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCouponFraudFlag extends Model
{
    protected $fillable = ['promotion_coupon_id', 'user_id', 'reason', 'status', 'evidence'];

    protected function casts(): array
    {
        return ['evidence' => 'array'];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(PromotionCoupon::class, 'promotion_coupon_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
