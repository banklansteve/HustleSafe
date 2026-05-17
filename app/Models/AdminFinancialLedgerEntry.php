<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdminFinancialLedgerEntry extends Model
{
    protected $fillable = [
        'uuid',
        'reference',
        'quest_id',
        'quest_offer_id',
        'client_id',
        'freelancer_id',
        'admin_user_id',
        'type',
        'direction',
        'source',
        'status',
        'description',
        'gross_amount_minor',
        'fee_amount_minor',
        'net_amount_minor',
        'balance_after_minor',
        'paystack_reference',
        'admin_reason',
        'meta',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdminFinancialLedgerEntry $entry): void {
            $entry->uuid ??= (string) Str::uuid();
            $entry->reference ??= self::generateReference();
            $entry->occurred_at ??= now();
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
            $reference = 'HSL-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(QuestOffer::class, 'quest_offer_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
