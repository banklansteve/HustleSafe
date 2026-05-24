<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffWatchlistItem extends Model
{
    protected $fillable = [
        'staff_user_id',
        'watchable_type',
        'watchable_id',
        'label',
        'notes',
        'priority',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function watchable(): MorphTo
    {
        return $this->morphTo();
    }
}
