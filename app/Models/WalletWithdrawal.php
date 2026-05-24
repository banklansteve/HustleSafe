<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletWithdrawal extends Model
{
    protected $fillable = [
        'uuid',
        'reference',
        'wallet_id',
        'user_id',
        'wallet_bank_account_id',
        'amount_minor',
        'fee_minor',
        'status',
        'paystack_transfer_code',
        'paystack_reference',
        'failure_reason',
        'processed_at',
        'meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (WalletWithdrawal $w): void {
            $w->uuid ??= (string) Str::uuid();
            $w->reference ??= self::generateReference();
        });
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'WDR-'.now()->format('ymd').'-'.Str::upper(Str::random(10));
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

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(WalletBankAccount::class, 'wallet_bank_account_id');
    }
}
