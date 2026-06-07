<?php

namespace App\Models;

use App\Enums\PremiumPatrolFlagStatus;
use App\Enums\PremiumPatrolFlagType;
use App\Enums\PremiumPatrolSubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PremiumPatrolFlag extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'flag_type',
        'severity',
        'status',
        'fingerprint',
        'meta',
        'detected_at',
        'auto_resolve_at',
        'dismissed_at',
        'dismissed_by_id',
        'dismissal_reason',
        'resolved_at',
        'resolved_by_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'detected_at' => 'datetime',
            'auto_resolve_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function typeEnum(): PremiumPatrolFlagType
    {
        return PremiumPatrolFlagType::from($this->flag_type);
    }

    public function subjectTypeEnum(): PremiumPatrolSubjectType
    {
        return PremiumPatrolSubjectType::from($this->subject_type);
    }

    public function statusEnum(): PremiumPatrolFlagStatus
    {
        return PremiumPatrolFlagStatus::from($this->status);
    }

    public function isOpen(): bool
    {
        return $this->status === PremiumPatrolFlagStatus::Open->value;
    }
}
