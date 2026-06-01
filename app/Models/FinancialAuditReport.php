<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinancialAuditReport extends Model
{
    protected $fillable = [
        'uuid',
        'type',
        'period_start',
        'period_end',
        'generated_by_user_id',
        'generated_at',
        'csv_path',
        'pdf_path',
        'summary',
    ];

    protected static function booted(): void
    {
        static::creating(function (FinancialAuditReport $report): void {
            $report->uuid ??= (string) Str::uuid();
            $report->generated_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
