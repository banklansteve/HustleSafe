<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffWatchlistItem extends Model
{
    protected $fillable = [
        'staff_user_id',
        'visibility',
        'watchable_type',
        'watchable_id',
        'label',
        'reason',
        'review_by_date',
        'severity',
        'notes',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'review_by_date' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function watchable(): MorphTo
    {
        return $this->morphTo();
    }
}
