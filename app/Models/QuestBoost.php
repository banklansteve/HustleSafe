<?php

namespace App\Models;

use App\Enums\QuestBoostStatus;
use App\Enums\QuestBoostTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QuestBoost extends Model
{
    protected $fillable = [
        'reference',
        'quest_id',
        'quest_title_snapshot',
        'client_id',
        'granted_by_admin_id',
        'purchased_by_client_id',
        'quest_boost_payment_id',
        'tier',
        'planned_cost_minor',
        'status',
        'starts_at',
        'ends_at',
        'grant_reason',
        'granted_at',
        'actual_ended_at',
        'visibility_tier',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'granted_at' => 'datetime',
            'actual_ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QuestBoost $boost): void {
            $boost->reference ??= self::generateReference();
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'QB-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function grantedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_admin_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(QuestBoostAuditLog::class)->orderBy('occurred_at');
    }

    public function tierEnum(): QuestBoostTier
    {
        return QuestBoostTier::from($this->tier);
    }

    public function statusEnum(): QuestBoostStatus
    {
        return QuestBoostStatus::from($this->status);
    }

    public function isActive(): bool
    {
        return $this->status === QuestBoostStatus::Active->value
            && $this->starts_at->lte(now())
            && $this->ends_at->gt(now());
    }

    public function scopeActiveNow($query)
    {
        return $query
            ->where('status', QuestBoostStatus::Active->value)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }
}
