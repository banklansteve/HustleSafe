<?php

namespace App\Models;

use App\Enums\QuestInstallmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestPaymentInstallment extends Model
{
    protected $fillable = [
        'quest_id',
        'installment_number',
        'period_start_at',
        'period_end_at',
        'amount_minor',
        'status',
        'delivered_at',
        'delivery_review_deadline_at',
        'delivery_revision_requested_at',
        'delivery_revision_note',
        'delivery_acknowledged_at',
        'latest_delivery_submission_id',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuestInstallmentStatus::class,
            'period_start_at' => 'datetime',
            'period_end_at' => 'datetime',
            'delivered_at' => 'datetime',
            'delivery_review_deadline_at' => 'datetime',
            'delivery_revision_requested_at' => 'datetime',
            'delivery_acknowledged_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function latestDeliverySubmission(): BelongsTo
    {
        return $this->belongsTo(QuestDeliverySubmission::class, 'latest_delivery_submission_id');
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [
            QuestInstallmentStatus::Active,
            QuestInstallmentStatus::AwaitingReview,
            QuestInstallmentStatus::RevisionRequested,
            QuestInstallmentStatus::Approved,
        ], true);
    }
}
