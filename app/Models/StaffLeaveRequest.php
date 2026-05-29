<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeaveRequest extends Model
{
    protected $fillable = [
        'staff_user_id',
        'leave_type',
        'duration_type',
        'start_date',
        'end_date',
        'days_requested',
        'hours_requested',
        'reason',
        'status',
        'reviewed_by_user_id',
        'review_note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'hours_requested' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
