<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffHrAuditTrail extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'action_type',
        'target_staff_user_id',
        'before_values',
        'after_values',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
