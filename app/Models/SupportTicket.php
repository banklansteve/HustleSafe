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
        'quest_conversation_thread_id',
        'opened_by_admin_id',
        'assigned_admin_id',
        'subject',
        'category',
        'priority',
        'status',
        'description',
        'resolution_summary',
        'opened_at',
        'closed_at',
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
            'closed_at' => 'datetime',
        ];
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
}
