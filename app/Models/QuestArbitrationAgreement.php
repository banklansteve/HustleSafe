<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestArbitrationAgreement extends Model
{
    protected $fillable = [
        'quest_id',
        'quest_offer_id',
        'user_id',
        'party',
        'agreed_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'agreed_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
