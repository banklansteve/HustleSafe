<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalReferenceTemplate extends Model
{
    protected $fillable = [
        'title',
        'quest_category_id',
        'body',
        'source_proposal_id',
        'created_by_id',
        'status',
        'quality_rating',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
