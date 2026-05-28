<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingQualityReviewAction extends Model
{
    protected $fillable = [
        'onboarding_quality_review_id',
        'admin_id',
        'action',
        'notes',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<OnboardingQualityReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(OnboardingQualityReview::class, 'onboarding_quality_review_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
