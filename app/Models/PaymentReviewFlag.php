<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReviewFlag extends Model
{
    protected $fillable = [
        'anomaly_type',
        'severity',
        'anomaly_fingerprint',
        'payment_escrow_id',
        'quest_id',
        'wallet_transaction_id',
        'transaction_reference',
        'signal_payload',
        'staff_admin_id',
        'concern_note',
        'resolution_status',
        'resolution_note',
        'resolved_by_admin_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'signal_payload' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staffAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_admin_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }

    /**
     * @return BelongsTo<PaymentEscrow, $this>
     */
    public function escrow(): BelongsTo
    {
        return $this->belongsTo(PaymentEscrow::class, 'payment_escrow_id');
    }

    /**
     * @return BelongsTo<Quest, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }
}
