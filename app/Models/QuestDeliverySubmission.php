<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestDeliverySubmission extends Model
{
    protected $fillable = [
        'quest_id',
        'freelancer_id',
        'revision_number',
        'summary',
        'delivery_url',
        'attachments',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'submitted_at' => 'datetime',
            'revision_number' => 'integer',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }
}
