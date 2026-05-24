<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffOnboardingAssistanceRecord extends Model
{
    protected $fillable = [
        'user_id',
        'user_type',
        'scenario',
        'milestone_reached',
        'status',
        'staleness_score',
        'cycles_elapsed',
        'last_meaningful_action_at',
        'last_activity_at',
        'fields_completed',
        'flow_metadata',
        'return_sessions_count',
        'assigned_staff_id',
        'contacted_at',
        'resolved_at',
        'next_cycle_at',
    ];

    protected function casts(): array
    {
        return [
            'fields_completed' => 'array',
            'flow_metadata' => 'array',
            'last_meaningful_action_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'contacted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'next_cycle_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }
}
