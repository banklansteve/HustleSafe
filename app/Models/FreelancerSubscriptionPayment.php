<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerSubscriptionPayment extends Model
{
    protected $fillable = [
        'freelancer_subscription_id',
        'user_id',
        'amount_minor',
        'billing_cycle',
        'paystack_reference',
        'status',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(FreelancerSubscription::class, 'freelancer_subscription_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
