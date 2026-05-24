<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestCompletionEvent extends Model
{
    protected $fillable = [
        'quest_id',
        'quest_offer_id',
        'actor_user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'meta',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(QuestOffer::class, 'quest_offer_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
