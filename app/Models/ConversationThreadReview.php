<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationThreadReview extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'quest_conversation_thread_id',
        'proposal_clarification_thread_id',
        'quest_id',
        'status',
        'priority',
        'trigger_categories',
        'flag_count',
        'first_flagged_at',
        'last_flagged_at',
        'assigned_staff_id',
        'super_admin_escalated_at',
        'super_admin_escalation_by',
        'super_admin_escalation_note',
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
            'super_admin_escalated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(QuestConversationThread::class, 'quest_conversation_thread_id');
    }

    public function clarificationThread(): BelongsTo
    {
        return $this->belongsTo(ProposalClarificationThread::class, 'proposal_clarification_thread_id');
    }

    public function isFocusedQa(): bool
    {
        return $this->proposal_clarification_thread_id !== null;
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function superAdminEscalationBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_escalation_by');
    }
}
