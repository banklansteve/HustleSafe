<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationDecision extends Model
{
    protected $fillable = [
        'moderation_case_id',
        'admin_user_id',
        'action',
        'reason_code',
        'note',
        'edited_snapshot',
        'time_to_decision_seconds',
    ];

    protected function casts(): array
    {
        return ['edited_snapshot' => 'array'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ModerationCase::class, 'moderation_case_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
