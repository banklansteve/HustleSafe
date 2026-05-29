<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffRoleAssignment extends Model
{
    protected $fillable = [
        'staff_user_id',
        'role_group',
        'starts_on',
        'ends_on',
        'status',
        'reason',
        'assigned_by_user_id',
        'revoked_at',
        'revoked_by_user_id',
        'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
