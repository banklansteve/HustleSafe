<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KycReviewCase extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'user_verification_id',
        'assigned_admin_id',
        'target_tier',
        'verification_type',
        'status',
        'priority',
        'queue_reason',
        'confidence_score',
        'submitted_snapshot',
        'provider_snapshot',
        'comparison',
        'entered_queue_at',
        'review_started_at',
        'decided_at',
        'decision',
        'decision_reason',
        'decision_note',
    ];

    protected static function booted(): void
    {
        static::creating(function (KycReviewCase $case): void {
            $case->uuid ??= (string) Str::uuid();
            $case->entered_queue_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'submitted_snapshot' => 'array',
            'provider_snapshot' => 'array',
            'comparison' => 'array',
            'entered_queue_at' => 'datetime',
            'review_started_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(UserVerification::class, 'user_verification_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(KycDecision::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(KycAuditEvent::class);
    }
}
