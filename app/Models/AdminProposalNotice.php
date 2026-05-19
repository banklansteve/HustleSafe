<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminProposalNotice extends Model
{
    protected $fillable = [
        'quest_offer_id',
        'created_by_admin_id',
        'type',
        'body',
        'visible_to_freelancer',
        'visible_to_client',
    ];

    protected function casts(): array
    {
        return [
            'visible_to_freelancer' => 'boolean',
            'visible_to_client' => 'boolean',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(QuestOffer::class, 'quest_offer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }
}
