<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PremiumPatrolWatchlist extends Model
{
    protected $table = 'premium_patrol_watchlist';

    protected $fillable = [
        'watchlist_type',
        'user_id',
        'reason',
        'added_by_id',
        'expires_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }
}
