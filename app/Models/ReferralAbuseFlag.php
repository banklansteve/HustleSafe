<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralAbuseFlag extends Model
{
    protected $fillable = ['user_referral_id', 'referrer_user_id', 'reason', 'status', 'evidence'];

    protected function casts(): array
    {
        return ['evidence' => 'array'];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(UserReferral::class, 'user_referral_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }
}
