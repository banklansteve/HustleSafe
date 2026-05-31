<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestContractDeliverable extends Model
{
    protected $fillable = [
        'quest_contract_id',
        'position',
        'title',
        'description',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(QuestContract::class, 'quest_contract_id');
    }
}
