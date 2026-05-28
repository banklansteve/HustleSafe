<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationUserHealthScore extends Model
{
    protected $fillable = [
        'user_id',
        'health_score',
        'flag_count_30d',
        'distinct_counterparties_30d',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'calculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
