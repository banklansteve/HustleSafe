<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    protected $fillable = [
        'uuid',
        'reference',
        'wallet_id',
        'user_id',
        'type',
        'direction',
        'amount_minor',
        'fee_minor',
        'balance_after_minor',
        'status',
        'paystack_reference',
        'idempotency_key',
        'escrow_id',
        'quest_id',
        'wallet_withdrawal_id',
        'admin_user_id',
        'description',
        'meta',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (WalletTransaction $tx): void {
            $tx->uuid ??= (string) Str::uuid();
            $tx->reference ??= self::generateReference();
            $tx->occurred_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'WTX-'.now()->format('ymd').'-'.Str::upper(Str::random(10));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function escrow(): BelongsTo
    {
        return $this->belongsTo(PaymentEscrow::class, 'escrow_id');
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }
}
