<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PremiumPatrolInvestigation extends Model
{
    protected $fillable = [
        'case_reference',
        'subject_type',
        'subject_id',
        'status',
        'assigned_to_id',
        'opened_by_id',
        'title',
        'timeline',
        'meta',
        'resolved_at',
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
        static::creating(function (PremiumPatrolInvestigation $case): void {
            $case->case_reference ??= self::generateReference();
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'PPI-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (self::query()->where('case_reference', $reference)->exists());

        return $reference;
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }
}
