<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Enums\ReviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'edit_window_ends_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'review_type' => ReviewType::class,
            'status' => ReviewStatus::class,
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

    public function isEditable(): bool
    {
        if ($this->status === ReviewStatus::Locked) {
            return false;
        }

        return $this->edit_window_ends_at !== null && $this->edit_window_ends_at->isFuture();
    }
}
