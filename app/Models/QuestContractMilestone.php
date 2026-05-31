<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestContractMilestone extends Model
{
    protected $fillable = [
        'quest_contract_id',
        'position',
        'name',
        'deliverable_reference',
        'value_minor',
        'deadline_date',
    ];

    protected function casts(): array
    {
        return [
            'deadline_date' => 'date',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(QuestContract::class, 'quest_contract_id');
    }
}
