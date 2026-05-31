<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminUserSanction extends Model
{
    protected $fillable = [
        'user_id',
        'admin_user_id',
        'type',
        'reason_code',
        'notes',
        'starts_at',
        'ends_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'user_acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reversed_at' => 'datetime',
            'user_acknowledged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
