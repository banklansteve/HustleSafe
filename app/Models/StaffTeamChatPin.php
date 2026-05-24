<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTeamChatPin extends Model
{
    protected $fillable = [
        'staff_team_chat_room_id',
        'staff_team_chat_message_id',
        'pinned_by_admin_id',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(StaffTeamChatMessage::class, 'staff_team_chat_message_id');
    }
}
