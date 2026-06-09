<?php

namespace App\Models;

use App\Enums\QuestPatrolFlagStatus;
use App\Enums\QuestPatrolFlagType;
use App\Enums\QuestPatrolSubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestPatrolFlag extends Model
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
        'dismissed_at',
        'dismissed_by_id',
        'dismissal_reason_code',
        'dismissal_reason',
        'resolved_at',
        'resolved_by_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'detected_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by_id');
    }

    public function typeEnum(): ?QuestPatrolFlagType
    {
        return QuestPatrolFlagType::tryFrom($this->flag_type);
    }

    public function subjectTypeEnum(): ?QuestPatrolSubjectType
    {
        return QuestPatrolSubjectType::tryFrom($this->subject_type);
    }

    public function isOpen(): bool
    {
        return $this->status === QuestPatrolFlagStatus::Open->value;
    }
}
