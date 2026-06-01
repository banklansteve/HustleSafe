<?php

namespace App\Models;

use App\Enums\LedgerAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'batch_id',
        'ledger_account',
        'side',
        'amount_minor',
        'currency',
        'payment_escrow_id',
        'quest_id',
        'wallet_withdrawal_id',
        'client_id',
        'freelancer_id',
        'paystack_reference',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (LedgerEntry $entry): void {
            $entry->uuid ??= (string) Str::uuid();
            $entry->occurred_at ??= now();
        });

        static::updating(function (): void {
            throw new \RuntimeException('Ledger entries are immutable.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Ledger entries cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LedgerJournalBatch::class, 'batch_id');
    }

    public function accountEnum(): ?LedgerAccount
    {
        return LedgerAccount::tryFrom($this->ledger_account);
    }

    public function accountLabel(): string
    {
        return $this->accountEnum()?->label() ?? $this->ledger_account;
    }
}
