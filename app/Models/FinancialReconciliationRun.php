<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FinancialReconciliationRun extends Model
{
    protected $fillable = [
        'uuid',
        'started_at',
        'finished_at',
        'status',
        'records_processed',
        'exceptions_found',
        'checks',
        'error_message',
    ];

    protected static function booted(): void
    {
        static::creating(function (FinancialReconciliationRun $run): void {
            $run->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'checks' => 'array',
        ];
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(FinancialReconciliationException::class, 'latest_run_id');
    }
}
