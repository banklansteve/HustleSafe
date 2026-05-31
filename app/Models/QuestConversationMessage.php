<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestConversationMessage extends Model
{
    protected $fillable = [
        'quest_conversation_thread_id',
        'user_id',
        'body',
        'body_original',
        'is_redacted',
        'redaction_label',
    ];

    protected function casts(): array
    {
        return [
            'is_redacted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<QuestConversationThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(QuestConversationThread::class, 'quest_conversation_thread_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
