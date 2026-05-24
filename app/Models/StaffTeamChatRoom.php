<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffTeamChatRoom extends Model
{
    protected $fillable = ['slug', 'name', 'type'];

    public function messages(): HasMany
    {
        return $this->hasMany(StaffTeamChatMessage::class)->orderBy('created_at');
    }

    public function pins(): HasMany
    {
        return $this->hasMany(StaffTeamChatPin::class);
    }
}
