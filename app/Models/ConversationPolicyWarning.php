<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationPolicyWarning extends Model
{
    protected $fillable = [
        'user_id',
        'thread_review_id',
        'issued_by',
        'note',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ConversationThreadReview::class, 'thread_review_id');
    }
}
