<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationApprovalRequest extends Model
{
    protected $fillable = [
        'request_type',
        'subject_type',
        'subject_id',
        'requested_by_id',
        'reason',
        'status',
        'reviewed_by_id',
        'reviewed_at',
        'review_notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
