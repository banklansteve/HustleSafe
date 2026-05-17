<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionCoupon extends Model
{
    protected $fillable = [
        'code',
        'status',
        'discount_type',
        'discount_value_minor',
        'discount_percent',
        'max_discount_minor',
        'applies_to',
        'quest_category_id',
        'eligibility',
        'eligible_user_ids',
        'usage_limit_total',
        'usage_limit_per_user',
        'usage_count',
        'starts_at',
        'ends_at',
        'minimum_transaction_minor',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'eligible_user_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestCategory::class, 'quest_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionCouponRedemption::class);
    }

    public function fraudFlags(): HasMany
    {
        return $this->hasMany(PromotionCouponFraudFlag::class);
    }
}
