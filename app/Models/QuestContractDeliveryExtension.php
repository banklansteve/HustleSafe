<?php

namespace App\Models;

use App\Enums\DeliveryDateAdjustmentType;
use App\Enums\DeliveryExtensionReasonCategory;
use App\Enums\DeliveryExtensionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestContractDeliveryExtension extends Model
{
    protected $fillable = [
        'quest_contract_id',
        'extension_number',
        'adjustment_type',
        'requested_by_user_id',
        'reason_category',
        'explanation',
        'original_delivery_date',
        'proposed_delivery_date',
        'status',
        'progress_note',
        'progress_attachments',
        'scope_change_message_id',
        'client_response_deadline_at',
        'counter_proposed_date',
        'counter_proposed_at',
        'counter_response_deadline_at',
        'decline_reason',
        'resolution',
        'applied_delivery_date',
        'quest_contract_amendment_id',
        'resolved_by_user_id',
        'resolved_at',
        'client_attributed_delay',
        'admin_monitoring_flagged',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_type' => DeliveryDateAdjustmentType::class,
            'reason_category' => DeliveryExtensionReasonCategory::class,
            'status' => DeliveryExtensionStatus::class,
            'original_delivery_date' => 'date',
            'proposed_delivery_date' => 'date',
            'counter_proposed_date' => 'date',
            'applied_delivery_date' => 'date',
            'progress_attachments' => 'array',
            'client_response_deadline_at' => 'datetime',
            'counter_proposed_at' => 'datetime',
            'counter_response_deadline_at' => 'datetime',
            'resolved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'client_attributed_delay' => 'boolean',
            'admin_monitoring_flagged' => 'boolean',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(QuestContract::class, 'quest_contract_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function amendment(): BelongsTo
    {
        return $this->belongsTo(QuestContractAmendment::class, 'quest_contract_amendment_id');
    }

    public function scopeChangeMessage(): BelongsTo
    {
        return $this->belongsTo(QuestConversationMessage::class, 'scope_change_message_id');
    }
}
