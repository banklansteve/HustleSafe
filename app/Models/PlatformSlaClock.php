<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class PlatformSlaClock extends Model
{
    protected $fillable = [
        'uuid',
        'sla_key',
        'subject_type',
        'subject_id',
        'assigned_admin_id',
        'triggered_by_user_id',
        'triggered_at',
        'due_at',
        'breached_at',
        'resolved_at',
        'escalated_at',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'due_at' => 'datetime',
            'breached_at' => 'datetime',
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PlatformSlaClock $clock): void {
            $clock->uuid ??= (string) Str::uuid();
        });
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBreached(): bool
    {
        return $this->status === 'breached' || ($this->breached_at !== null && $this->resolved_at === null);
    }
}
