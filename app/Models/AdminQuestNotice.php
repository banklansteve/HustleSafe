<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminQuestNotice extends Model
{
    protected $fillable = [
        'quest_id',
        'created_by_admin_id',
        'type',
        'body',
        'visible_to_users',
    ];

    protected function casts(): array
    {
        return [
            'visible_to_users' => 'boolean',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }
}
