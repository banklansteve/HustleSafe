<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffNotificationPreference extends Model
{
    protected $fillable = [
        'staff_user_id',
        'preferences',
    ];

    protected function casts(): array
    {
        return ['preferences' => 'array'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
