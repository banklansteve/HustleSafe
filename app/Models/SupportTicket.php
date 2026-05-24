<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'customer_username',
        'customer_full_name',
        'quest_conversation_thread_id',
        'opened_by_admin_id',
        'assigned_admin_id',
        'subject',
        'category',
        'priority',
        'status',
        'chat_status',
        'description',
        'resolution_summary',
        'opened_at',
        'queued_at',
        'closed_at',
        'last_activity_at',
        'last_user_activity_at',
        'last_admin_activity_at',
        'user_last_read_message_id',
        'admin_last_read_message_id',
        'rating_stars',
        'rating_score',
        'rating_reaction',
        'rating_comment',
        'feedback_answers',
        'rated_at',
        'rating_email_sent_at',
        'resolution_seconds',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            $ticket->uuid ??= (string) Str::uuid();
            $ticket->opened_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'queued_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'last_user_activity_at' => 'datetime',
            'last_admin_activity_at' => 'datetime',
            'rated_at' => 'datetime',
            'rating_email_sent_at' => 'datetime',
            'feedback_answers' => 'array',
        ];
    }

    public function isLiveChat(): bool
    {
        return $this->opened_by_admin_id === null && $this->user_id !== null;
    }

    public function isClosed(): bool
    {
        return in_array($this->chat_status, ['closed'], true) || $this->status === 'closed';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(QuestConversationThread::class, 'quest_conversation_thread_id');
    }

    public function openedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_admin_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    public function handoffs(): HasMany
    {
        return $this->hasMany(SupportTicketHandoff::class);
    }
}
