<?php

namespace App\Models;

use App\Enums\DisputeSettlementOfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeSettlementOffer extends Model
{
    protected $fillable = [
        'quest_dispute_id',
        'offered_by_user_id',
        'client_share_percent',
        'note',
        'status',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisputeSettlementOfferStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<QuestDispute, $this>
     */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(QuestDispute::class, 'quest_dispute_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by_user_id');
    }
}
