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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ConversationThreadReview::class, 'thread_review_id');
    }
}
