<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffOnboardingOutreach extends Model
{
    protected $table = 'staff_onboarding_outreach';

    protected $fillable = [
        'user_id',
        'scenario',
        'status',
        'friction_point',
        'context',
        'assigned_staff_id',
        'contacted_by_staff_id',
        'contacted_at',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'contacted_at' => 'datetime',
            'converted_at' => 'datetime',
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
