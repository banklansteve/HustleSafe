<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityPatrolNote extends Model
{
    protected $fillable = [
        'user_id',
        'flag_id',
        'author_id',
        'body',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flag(): BelongsTo
    {
        return $this->belongsTo(UserActivityPatrolFlag::class, 'flag_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
