<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationKeyword extends Model
{
    protected $fillable = ['phrase', 'severity', 'category', 'is_active', 'note'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
