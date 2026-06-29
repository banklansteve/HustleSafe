<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputePrecedent extends Model
{
    protected $fillable = [
        'quest_dispute_id',
        'created_by_user_id',
        'title',
        'summary',
        'category',
        'linked_dispute_ids',
    ];

    protected function casts(): array
    {
        return [
            'linked_dispute_ids' => 'array',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(QuestDispute::class, 'quest_dispute_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
