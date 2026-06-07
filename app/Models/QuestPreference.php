<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestPreference extends Model
{
    protected $fillable = [
        'quest_id',
        'preference_key',
        'preference_value',
        'is_specified',
    ];

    protected function casts(): array
    {
        return [
            'preference_value' => 'array',
            'is_specified' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Quest, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /**
     * @return HasMany<ProposalPreferenceResponse, $this>
     */
    public function proposalResponses(): HasMany
    {
        return $this->hasMany(ProposalPreferenceResponse::class);
    }
}
