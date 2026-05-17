<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationNotificationTemplate extends Model
{
    protected $fillable = ['key', 'label', 'subject', 'body', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
