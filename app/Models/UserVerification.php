<?php

namespace App\Models;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserVerification extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'target_tier',
        'freelancer_credential_id',
        'status',
        'provider',
        'provider_reference',
        'document_paths',
        'metadata',
        'provider_response',
        'confidence_score',
        'queue_reason',
        'attempt_count',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => UserVerificationCategory::class,
            'status' => UserVerificationStatus::class,
            'document_paths' => 'array',
            'metadata' => 'array',
            'provider_response' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<FreelancerCredential, $this>
     */
    public function freelancerCredential(): BelongsTo
    {
        return $this->belongsTo(FreelancerCredential::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function kycReviewCases(): HasMany
    {
        return $this->hasMany(KycReviewCase::class);
    }
}
