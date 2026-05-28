<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationSystematicEscalation extends Model
{
    protected $fillable = [
        'user_id',
        'trigger_category',
        'status',
        'instance_count',
        'distinct_counterparties',
        'distinct_contracts',
        'timeline',
        'resolution_note',
        'resolved_by',
        'resolved_at',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'timeline' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
