<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSanctionAppeal extends Model
{
    protected $fillable = [
        'admin_user_sanction_id',
        'user_id',
        'statement',
        'evidence',
        'status',
        'assigned_staff_id',
        'reviewed_by_staff_id',
        'escalated_to_admin_id',
        'decision_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function sanction(): BelongsTo
    {
        return $this->belongsTo(AdminUserSanction::class, 'admin_user_sanction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
