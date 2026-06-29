<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeMediationSession extends Model
{
    protected $fillable = [
        'quest_dispute_id',
        'opened_by_user_id',
        'status',
        'scheduled_at',
        'meeting_url',
        'instructions',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(QuestDispute::class, 'quest_dispute_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }
}
