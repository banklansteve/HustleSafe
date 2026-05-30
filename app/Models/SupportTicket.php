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
        'ticket_reference',
        'user_id',
        'customer_username',
        'customer_full_name',
        'quest_conversation_thread_id',
        'opened_by_admin_id',
        'assigned_admin_id',
        'subject',
        'category',
        'issue_group',
        'priority',
        'status',
        'chat_status',
        'description',
        'internal_notes',
        'action_items',
        'resolution_summary',
        'opened_at',
        'in_progress_at',
        'expected_resolution_at',
        'sla_breached',
        'sla_overdue_at',
        'sla_override_reason',
        'sla_override_by_user_id',
        'merged_into_support_ticket_id',
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
            'in_progress_at' => 'datetime',
            'expected_resolution_at' => 'datetime',
            'sla_breached' => 'boolean',
            'sla_overdue_at' => 'datetime',
            'action_items' => 'array',
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
        return in_array($this->chat_status, ['closed'], true)
            || in_array($this->status, ['closed', 'resolved'], true);
    }

    public function isReadOnly(): bool
    {
        return $this->merged_into_support_ticket_id !== null;
    }

    public function isManagedTicket(): bool
    {
        return $this->opened_by_admin_id !== null;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        $field ??= $this->getRouteKeyName();

        if ($field === 'uuid' && is_numeric($value)) {
            return $this->newQuery()->whereKey((int) $value)->first();
        }

        return parent::resolveRouteBinding($value, $field);
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

    public function activities(): HasMany
    {
        return $this->hasMany(SupportTicketActivity::class)->orderByDesc('occurred_at');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(SupportTicketEmailLog::class)->orderByDesc('sent_at');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_support_ticket_id');
    }

    public function slaOverrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sla_override_by_user_id');
    }
}
