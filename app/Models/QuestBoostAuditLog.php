<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestBoostAuditLog extends Model
{
    protected $fillable = [
        'quest_boost_id',
        'action_type',
        'actor_user_id',
        'reason',
        'old_values',
        'new_values',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function boost(): BelongsTo
    {
        return $this->belongsTo(QuestBoost::class, 'quest_boost_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
