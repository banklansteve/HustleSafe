<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeAppeal extends Model
{
    protected $fillable = [
        'quest_dispute_id',
        'filed_by_user_id',
        'party_role',
        'unfair_reason',
        'proposed_option',
        'proposed_terms',
        'counter_response',
        'counter_by_user_id',
        'counter_responded_at',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_outcome_notes',
        'upheld_original',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_terms' => 'array',
            'counter_responded_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'upheld_original' => 'boolean',
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
    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by_user_id');
    }
}
