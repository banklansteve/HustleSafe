<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffFreelancerQualityFlag extends Model
{
    protected $fillable = [
        'freelancer_id',
        'staff_user_id',
        'status',
        'trigger_reason',
        'metrics_snapshot',
        'trend_snapshot',
        'staff_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'metrics_snapshot' => 'array',
            'trend_snapshot' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
