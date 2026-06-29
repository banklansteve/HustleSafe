<?php

namespace App\Models;

use App\Enums\DisputeAssessmentRecommendation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeAssessment extends Model
{
    protected $fillable = [
        'quest_dispute_id',
        'staff_user_id',
        'quality_rating',
        'investigation_checklist',
        'violation_status',
        'key_findings',
        'recommendation',
        'recommended_client_share_percent',
        'reasoning',
        'time_spent_minutes',
        'super_admin_rating',
        'super_admin_feedback',
        'recommended_sanction',
        'alternate_recommendations',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'investigation_checklist' => 'array',
            'key_findings' => 'array',
            'alternate_recommendations' => 'array',
            'recommendation' => DisputeAssessmentRecommendation::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(QuestDispute::class, 'quest_dispute_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
