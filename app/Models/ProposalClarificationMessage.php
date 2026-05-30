<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalClarificationMessage extends Model
{
    protected $fillable = [
        'thread_id',
        'author_user_id',
        'role',
        'prompt_key',
        'prompt_category',
        'body',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ProposalClarificationThread::class, 'thread_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
