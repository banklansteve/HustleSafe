<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffTeamChatMessage extends Model
{
    protected $fillable = [
        'staff_team_chat_room_id',
        'user_id',
        'body',
        'attachments',
        'mentions',
        'is_official_guidance',
        'removed_by_admin_id',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'mentions' => 'array',
            'is_official_guidance' => 'boolean',
            'removed_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(StaffTeamChatRoom::class, 'staff_team_chat_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(StaffTeamChatReaction::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(StaffTeamChatRead::class);
    }
}
