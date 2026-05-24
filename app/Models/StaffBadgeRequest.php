<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffBadgeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'badge_slug',
        'status',
        'applicant_note',
        'metrics_snapshot',
        'reviewed_by_staff_id',
        'decision_note',
        'escalated_to_super_admin',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'metrics_snapshot' => 'array',
            'escalated_to_super_admin' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
