<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ModerationCase extends Model
{
    protected $fillable = [
        'uuid',
        'moderatable_type',
        'moderatable_id',
        'subject_user_id',
        'reporter_user_id',
        'assigned_admin_id',
        'content_type',
        'queue',
        'status',
        'severity',
        'visibility_state',
        'source',
        'confidence',
        'title',
        'excerpt',
        'snapshot',
        'entered_queue_at',
        'review_started_at',
        'decided_at',
        'decision',
        'decision_reason',
        'decision_note',
    ];

    protected static function booted(): void
    {
        static::creating(function (ModerationCase $case): void {
            $case->uuid ??= (string) Str::uuid();
            $case->entered_queue_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'entered_queue_at' => 'datetime',
            'review_started_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function moderatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(ModerationCaseTrigger::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ModerationDecision::class);
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(ModerationAppeal::class);
    }
}
