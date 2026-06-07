<?php

namespace App\Models;

use App\Enums\FreelancerSubscriptionStatus;
use App\Enums\FreelancerSubscriptionTier;
use App\Enums\SubscriptionBillingCycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreelancerSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'tier',
        'started_at',
        'renewal_date',
        'billing_cycle',
        'monthly_price_minor',
        'annual_price_minor',
        'auto_renew',
        'payment_method_snapshot',
        'total_spent_minor',
        'cancelled_at',
        'cancellation_reason',
        'admin_suspended_at',
        'admin_suspended_by_id',
        'admin_suspension_reason',
        'manual_review_until',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'renewal_date' => 'datetime',
            'auto_renew' => 'boolean',
            'payment_method_snapshot' => 'array',
            'cancelled_at' => 'datetime',
            'admin_suspended_at' => 'datetime',
            'manual_review_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FreelancerSubscriptionPayment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(FreelancerSubscriptionHistory::class)->orderByDesc('occurred_at');
    }

    public function statusEnum(): FreelancerSubscriptionStatus
    {
        return FreelancerSubscriptionStatus::from($this->status);
    }

    public function tierEnum(): FreelancerSubscriptionTier
    {
        return FreelancerSubscriptionTier::from($this->tier);
    }

    public function billingCycleEnum(): ?SubscriptionBillingCycle
    {
        return $this->billing_cycle ? SubscriptionBillingCycle::from($this->billing_cycle) : null;
    }

    public function isProActive(): bool
    {
        return $this->tier === FreelancerSubscriptionTier::Pro->value
            && $this->status === FreelancerSubscriptionStatus::Active->value
            && ($this->renewal_date === null || $this->renewal_date->isFuture());
    }
}
