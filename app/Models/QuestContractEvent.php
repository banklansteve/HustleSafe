<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestContractEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'quest_contract_id',
        'user_id',
        'event_type',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(QuestContract::class, 'quest_contract_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
