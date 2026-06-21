<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationPolicyWarningReply extends Model
{
    protected $fillable = [
        'conversation_policy_warning_id',
        'user_id',
        'body',
    ];

    public function warning(): BelongsTo
    {
        return $this->belongsTo(ConversationPolicyWarning::class, 'conversation_policy_warning_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
