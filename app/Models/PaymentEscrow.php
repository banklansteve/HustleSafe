<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentEscrow extends Model
{
    protected $fillable = [
        'uuid',
        'reference',
        'quest_id',
        'quest_offer_id',
        'client_id',
        'freelancer_id',
        'amount_minor',
        'fee_minor',
        'released_minor',
        'refunded_minor',
        'currency',
        'status',
        'paystack_reference',
        'paystack_access_code',
        'funded_at',
        'released_at',
        'refunded_at',
        'cancelled_at',
        'meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentEscrow $escrow): void {
            $escrow->uuid ??= (string) Str::uuid();
            $escrow->reference ??= self::generateReference();
        });
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'funded_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'ESC-'.now()->format('ymd').'-'.Str::upper(Str::random(10));
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

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'escrow_id');
    }

    public function isFunded(): bool
    {
        return in_array($this->status, ['funded', 'held', 'released', 'partially_released'], true);
    }

    public function releasableMinor(): int
    {
        return max(0, (int) $this->amount_minor - (int) $this->released_minor - (int) $this->refunded_minor);
    }
}
