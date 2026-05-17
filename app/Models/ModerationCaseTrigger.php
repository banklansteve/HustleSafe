<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationCaseTrigger extends Model
{
    protected $fillable = [
        'moderation_case_id',
        'rule_key',
        'rule_type',
        'category',
        'severity',
        'confidence',
        'matched_text',
        'context',
        'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ModerationCase::class, 'moderation_case_id');
    }
}
