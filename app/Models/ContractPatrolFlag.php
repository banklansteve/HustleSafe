<?php

namespace App\Models;

use App\Enums\ContractPatrolFlagStatus;
use App\Enums\ContractPatrolFlagType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractPatrolFlag extends Model
{
    protected $fillable = [
        'quest_contract_id',
        'flag_type',
        'severity',
        'status',
        'fingerprint',
        'summary',
        'meta',
        'assigned_to_id',
        'detected_at',
        'acknowledged_at',
        'acknowledged_by_id',
        'dismissed_at',
        'dismissed_by_id',
        'dismissal_reason',
        'resolved_at',
        'resolved_by_id',
    ];

    protected function casts(): array
    {
        return [
            'flag_type' => ContractPatrolFlagType::class,
            'status' => ContractPatrolFlagStatus::class,
            'meta' => 'array',
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(QuestContract::class, 'quest_contract_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }
}
