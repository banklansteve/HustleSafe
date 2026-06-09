<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuestPatrolInvestigation extends Model
{
    protected $fillable = [
        'case_reference',
        'subject_type',
        'subject_id',
        'status',
        'opened_by_id',
        'assigned_to_id',
        'title',
        'severity',
        'timeline',
        'meta',
        'resolved_at',
        'resolved_by_id',
    ];

    protected function casts(): array
    {
        return [
            'timeline' => 'array',
            'meta' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QuestPatrolInvestigation $case): void {
            $case->case_reference ??= self::generateReference();
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'QPI-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (self::query()->where('case_reference', $reference)->exists());

        return $reference;
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
