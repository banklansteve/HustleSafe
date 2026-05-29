<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffHrSuspiciousActivityFlag extends Model
{
    protected $table = 'staff_hr_suspicious_activity_flags';

    protected $fillable = [
        'staff_user_id',
        'staff_session_log_id',
        'pattern',
        'note',
        'flagged_by_user_id',
        'flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'flagged_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
