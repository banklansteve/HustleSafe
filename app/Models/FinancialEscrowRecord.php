<?php

namespace App\Models;

use App\Enums\FinancialEscrowRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FinancialEscrowRecord extends Model
{
    protected $fillable = [
        'uuid',
        'escrow_reference',
        'payment_escrow_id',
        'quest_id',
        'quest_contract_id',
        'contract_reference',
        'quest_title',
        'quest_category_id',
        'client_id',
        'client_name',
        'freelancer_id',
        'freelancer_name',
        'gross_contract_value_minor',
        'total_funded_minor',
        'platform_fee_percent',
        'platform_fee_minor',
        'vat_percent',
        'vat_minor',
        'freelancer_net_minor',
        'gateway_name',
        'paystack_reference',
        'funded_at',
        'status',
        'release_trigger_type',
        'released_at',
        'refunded_at',
        'wallet_credit_reference',
        'fee_recognised_at',
        'meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (FinancialEscrowRecord $record): void {
            $record->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'platform_fee_percent' => 'float',
            'vat_percent' => 'float',
            'funded_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
            'fee_recognised_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function paymentEscrow(): BelongsTo
    {
        return $this->belongsTo(PaymentEscrow::class);
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(QuestContract::class, 'quest_contract_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestCategory::class, 'quest_category_id');
    }

    public function ledgerBatches(): HasMany
    {
        return $this->hasMany(LedgerJournalBatch::class, 'payment_escrow_id', 'payment_escrow_id')
            ->orderBy('occurred_at');
    }

    public function statusEnum(): FinancialEscrowRecordStatus
    {
        return FinancialEscrowRecordStatus::tryFrom($this->status) ?? FinancialEscrowRecordStatus::Held;
    }
}
