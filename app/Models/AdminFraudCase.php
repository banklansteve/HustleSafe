<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdminFraudCase extends Model
{
    protected $fillable = [
        'user_id',
        'assigned_to_admin_id',
        'case_number',
        'risk_type',
        'risk_score',
        'status',
        'summary',
        'signals',
        'resolved_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdminFraudCase $case): void {
            $case->case_number ??= 'FRC-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        });
    }

    protected function casts(): array
    {
        return [
            'signals' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_admin_id');
    }
}
