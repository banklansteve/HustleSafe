<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityPatrolAction extends Model
{
    protected $fillable = [
        'user_id',
        'flag_id',
        'action_type',
        'actor_id',
        'reason_code',
        'reason_notes',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flag(): BelongsTo
    {
        return $this->belongsTo(UserActivityPatrolFlag::class, 'flag_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
