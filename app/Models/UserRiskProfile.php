<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRiskProfile extends Model
{
    protected $fillable = [
        'user_id',
        'composite_score',
        'tier',
        'breakdown',
        'signals',
        'in_risk_queue',
        'queued_at',
        'calculated_at',
        'previous_score',
    ];

    protected function casts(): array
    {
        return [
            'breakdown' => 'array',
            'signals' => 'array',
            'in_risk_queue' => 'boolean',
            'queued_at' => 'datetime',
            'calculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
