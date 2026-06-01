<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerSubscriptionHistory extends Model
{
    protected $fillable = [
        'freelancer_subscription_id',
        'user_id',
        'event',
        'from_status',
        'to_status',
        'actor_user_id',
        'meta',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(FreelancerSubscription::class, 'freelancer_subscription_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
