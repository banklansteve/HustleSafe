<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LedgerJournalBatch extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'reference',
        'event_type',
        'idempotency_key',
        'payment_escrow_id',
        'quest_id',
        'quest_contract_id',
        'wallet_withdrawal_id',
        'client_id',
        'freelancer_id',
        'paystack_reference',
        'description',
        'created_by_process',
        'reverses_batch_id',
        'reversal_reason',
        'meta',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (LedgerJournalBatch $batch): void {
            $batch->uuid ??= (string) Str::uuid();
            $batch->reference ??= self::generateReference();
            $batch->occurred_at ??= now();
        });

        static::updating(function (): void {
            throw new \RuntimeException('Ledger journal batches are immutable.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Ledger journal batches cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'LJB-'.now()->format('ymd').'-'.Str::upper(Str::random(10));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'batch_id');
    }

    public function paymentEscrow(): BelongsTo
    {
        return $this->belongsTo(PaymentEscrow::class);
    }

    public function reversedBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_batch_id');
    }
}
