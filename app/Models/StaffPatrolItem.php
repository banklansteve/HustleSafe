<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffPatrolItem extends Model
{
    protected $fillable = [
        'staff_patrol_session_id',
        'reviewable_type',
        'reviewable_id',
        'decision',
        'notes',
        'risk_signals',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_signals' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(StaffPatrolSession::class, 'staff_patrol_session_id');
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}
