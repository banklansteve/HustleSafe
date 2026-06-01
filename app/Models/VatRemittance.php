<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VatRemittance extends Model
{
    protected $fillable = [
        'uuid',
        'quarter_label',
        'period_start',
        'period_end',
        'amount_minor',
        'remittance_reference',
        'remitted_at',
        'recorded_by_user_id',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (VatRemittance $remittance): void {
            $remittance->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'remitted_at' => 'datetime',
        ];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
