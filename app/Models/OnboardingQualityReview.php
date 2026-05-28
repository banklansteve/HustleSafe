<?php

namespace App\Models;

use App\Enums\OnboardingQualityReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingQualityReview extends Model
{
    protected $fillable = [
        'user_id',
        'user_type',
        'status',
        'completeness_score',
        'auto_flags',
        'manual_flag_overrides',
        'monitoring_flagged',
        'monitoring_reason',
        'review_deadline_at',
        'last_evaluated_at',
        'status_changed_at',
        'assigned_admin_id',
        'last_action_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => OnboardingQualityReviewStatus::class,
            'auto_flags' => 'array',
            'manual_flag_overrides' => 'array',
            'monitoring_flagged' => 'boolean',
            'review_deadline_at' => 'datetime',
            'last_evaluated_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    /**
     * @return HasMany<OnboardingQualityReviewAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(OnboardingQualityReviewAction::class)->latest();
    }

    public function blocksPosting(): bool
    {
        return $this->status instanceof OnboardingQualityReviewStatus
            && $this->status->blocksPosting();
    }
}
