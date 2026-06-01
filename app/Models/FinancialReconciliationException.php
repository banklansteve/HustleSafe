<?php

namespace App\Models;

use App\Enums\ReconciliationExceptionStatus;
use App\Enums\ReconciliationExceptionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinancialReconciliationException extends Model
{
    protected $fillable = [
        'uuid',
        'first_run_id',
        'latest_run_id',
        'type',
        'status',
        'assigned_to_user_id',
        'payment_escrow_id',
        'paystack_reference',
        'variance_minor',
        'title',
        'description',
        'investigation_notes',
        'resolution_description',
        'first_detected_at',
        'resolved_at',
        'resolved_by_user_id',
        'escalated_at',
        'meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (FinancialReconciliationException $exception): void {
            $exception->uuid ??= (string) Str::uuid();
            $exception->first_detected_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'first_detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function firstRun(): BelongsTo
    {
        return $this->belongsTo(FinancialReconciliationRun::class, 'first_run_id');
    }

    public function latestRun(): BelongsTo
    {
        return $this->belongsTo(FinancialReconciliationRun::class, 'latest_run_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function paymentEscrow(): BelongsTo
    {
        return $this->belongsTo(PaymentEscrow::class);
    }

    public function typeEnum(): ?ReconciliationExceptionType
    {
        return ReconciliationExceptionType::tryFrom($this->type);
    }

    public function statusEnum(): ReconciliationExceptionStatus
    {
        return ReconciliationExceptionStatus::tryFrom($this->status) ?? ReconciliationExceptionStatus::Open;
    }
}
