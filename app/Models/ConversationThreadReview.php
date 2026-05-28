<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationThreadReview extends Model
{
    protected $fillable = [
        'quest_conversation_thread_id',
        'quest_id',
        'status',
        'priority',
        'trigger_categories',
        'flag_count',
        'first_flagged_at',
        'last_flagged_at',
        'assigned_staff_id',
        'escalated_to_admin_id',
        'escalated_at',
        'dismiss_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_categories' => 'array',
            'first_flagged_at' => 'datetime',
            'last_flagged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(QuestConversationThread::class, 'quest_conversation_thread_id');
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(ConversationMessageFlag::class, 'quest_conversation_thread_id', 'quest_conversation_thread_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }
}
