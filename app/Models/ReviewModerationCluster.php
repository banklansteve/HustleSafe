<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewModerationCluster extends Model
{
    protected $fillable = [
        'cluster_type',
        'primary_reviewee_id',
        'metadata',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function primaryReviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_reviewee_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'moderation_cluster_id');
    }
}
