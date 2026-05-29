<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffHrAlert extends Model
{
    protected $table = 'staff_hr_alerts';

    protected $fillable = [
        'staff_user_id',
        'alert_type',
        'severity',
        'message',
        'payload',
        'triggered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'triggered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
