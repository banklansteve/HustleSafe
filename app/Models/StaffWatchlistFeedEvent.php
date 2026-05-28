<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffWatchlistFeedEvent extends Model
{
    protected $fillable = [
        'watched_user_id',
        'staff_watchlist_item_id',
        'event_type',
        'severity',
        'title',
        'summary',
        'entity_type',
        'entity_id',
        'action_url',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function watchedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'watched_user_id');
    }

    public function watchlistItem(): BelongsTo
    {
        return $this->belongsTo(StaffWatchlistItem::class, 'staff_watchlist_item_id');
    }
}
