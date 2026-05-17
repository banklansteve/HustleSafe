<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    protected $fillable = [
        'user_referral_id',
        'user_id',
        'reward_type',
        'amount_minor',
        'status',
        'expires_at',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(UserReferral::class, 'user_referral_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
