<?php

namespace App\Models;

use App\Enums\ReviewAuthenticityFlag;
use App\Enums\ReviewStatus;
use App\Enums\ReviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Review extends Model
{
    protected $fillable = [
        'quest_id',
        'reviewer_id',
        'reviewee_id',
        'reviewer_party',
        'review_type',
        'rating',
        'title',
        'comment',
        'tags',
        'status',
        'authenticity_flag',
        'quality_score',
        'is_brief',
        'sentiment_score',
        'reviewer_subnet',
        'moderation_cluster_id',
        'edit_window_ends_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'review_type' => ReviewType::class,
            'status' => ReviewStatus::class,
            'authenticity_flag' => ReviewAuthenticityFlag::class,
            'is_brief' => 'boolean',
            'quality_score' => 'integer',
            'sentiment_score' => 'float',
            'edit_window_ends_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quest, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }

    /**
     * @return HasMany<ReviewAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReviewAttachment::class);
    }

    /**
     * @return MorphMany<ModerationCase, $this>
     */
    public function moderationCases(): MorphMany
    {
        return $this->morphMany(ModerationCase::class, 'moderatable');
    }

    /**
     * @return BelongsTo<ReviewModerationCluster, $this>
     */
    public function moderationCluster(): BelongsTo
    {
        return $this->belongsTo(ReviewModerationCluster::class, 'moderation_cluster_id');
    }

    /**
     * @return HasMany<ReviewAuthenticitySignal, $this>
     */
    public function authenticitySignals(): HasMany
    {
        return $this->hasMany(ReviewAuthenticitySignal::class);
    }

    /**
     * @return HasMany<ReviewAmendmentRequest, $this>
     */
    public function amendmentRequests(): HasMany
    {
        return $this->hasMany(ReviewAmendmentRequest::class);
    }

    public function countsTowardRatings(): bool
    {
        return $this->status === ReviewStatus::Published;
    }

    public function isEditable(): bool
    {
        if ($this->status === ReviewStatus::Locked) {
            return false;
        }

        return $this->edit_window_ends_at !== null && $this->edit_window_ends_at->isFuture();
    }
}
