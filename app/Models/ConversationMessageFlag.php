<?php

namespace App\Models;

use App\Enums\ConversationFlagCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessageFlag extends Model
{
    protected $fillable = [
        'quest_conversation_thread_id',
        'quest_conversation_message_id',
        'sender_user_id',
        'quest_id',
        'quest_offer_id',
        'trigger_category',
        'matched_pattern_redacted',
        'confidence',
        'status',
        'flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_category' => ConversationFlagCategory::class,
            'confidence' => 'float',
            'flagged_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(QuestConversationThread::class, 'quest_conversation_thread_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(QuestConversationMessage::class, 'quest_conversation_message_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
