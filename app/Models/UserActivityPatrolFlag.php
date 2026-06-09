<?php

namespace App\Models;

use App\Enums\UserActivityAnomalyType;
use App\Enums\UserActivityPatrolStatus;
use App\Enums\UserActivityRiskLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserActivityPatrolFlag extends Model
{
    protected $fillable = [
        'user_id',
        'anomaly_type',
        'risk_level',
        'risk_score',
        'status',
        'assigned_to_id',
        'fingerprint',
        'summary',
        'meta',
        'detected_at',
        'resolved_at',
        'resolved_by_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(UserActivityPatrolAction::class, 'flag_id');
    }

    public function anomalyTypeEnum(): UserActivityAnomalyType
    {
        return UserActivityAnomalyType::from($this->anomaly_type);
    }

    public function statusEnum(): UserActivityPatrolStatus
    {
        return UserActivityPatrolStatus::from($this->status);
    }

    public function riskLevelEnum(): UserActivityRiskLevel
    {
        return UserActivityRiskLevel::from($this->risk_level);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            UserActivityPatrolStatus::Open->value,
            UserActivityPatrolStatus::UnderReview->value,
            UserActivityPatrolStatus::Watchlisted->value,
        ], true);
    }
}
