<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeliveryMetric extends Model
{
    protected $fillable = [
        'user_id',
        'on_time_completed_count',
        'total_completed_count',
        'reliability_score',
        'low_reliability_flagged',
        'extension_pattern_flagged',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'reliability_score' => 'decimal:2',
            'low_reliability_flagged' => 'boolean',
            'extension_pattern_flagged' => 'boolean',
            'calculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
