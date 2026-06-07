<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalPreferenceResponse extends Model
{
    protected $fillable = [
        'quest_offer_id',
        'quest_preference_id',
        'response_type',
        'response_text',
    ];

    /**
     * @return BelongsTo<QuestOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(QuestOffer::class, 'quest_offer_id');
    }

    /**
     * @return BelongsTo<QuestPreference, $this>
     */
    public function questPreference(): BelongsTo
    {
        return $this->belongsTo(QuestPreference::class);
    }
}
