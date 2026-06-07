<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestBoostPayment extends Model
{
    protected $fillable = [
        'quest_id',
        'client_id',
        'tier',
        'amount_minor',
        'paystack_reference',
        'status',
        'quest_boost_id',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function questBoost(): BelongsTo
    {
        return $this->belongsTo(QuestBoost::class, 'quest_boost_id');
    }
}
