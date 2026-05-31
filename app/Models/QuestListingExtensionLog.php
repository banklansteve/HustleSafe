<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestListingExtensionLog extends Model
{
    protected $fillable = [
        'quest_id',
        'client_user_id',
        'days_added',
        'previous_expires_at',
        'new_expires_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'previous_expires_at' => 'datetime',
            'new_expires_at' => 'datetime',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }
}
