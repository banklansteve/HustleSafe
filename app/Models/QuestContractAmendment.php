<?php

namespace App\Models;

use App\Enums\ContractAmendmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestContractAmendment extends Model
{
    protected $fillable = [
        'quest_contract_id',
        'amendment_number',
        'requested_by_user_id',
        'amendment_type',
        'description',
        'reason',
        'original_value',
        'new_value',
        'status',
        'response_note',
        'responded_by_user_id',
        'responded_at',
        'applied_terms_delta',
    ];

    protected function casts(): array
    {
        return [
            'amendment_type' => ContractAmendmentType::class,
            'responded_at' => 'datetime',
            'applied_terms_delta' => 'array',
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

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
