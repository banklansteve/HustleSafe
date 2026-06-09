<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccountMerge extends Model
{
    protected $fillable = [
        'primary_user_id',
        'secondary_user_id',
        'actor_id',
        'reason_code',
        'reason_notes',
        'meta',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'merged_at' => 'datetime',
        ];
    }

    public function primaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_user_id');
    }

    public function secondaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secondary_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
