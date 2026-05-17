<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestConversationThread extends Model
{
    protected $fillable = [
        'quest_id',
        'freelancer_id',
        'client_id',
        'messages_count',
        'last_message_at',
        'freelancer_last_read_at',
        'client_last_read_at',
        'admin_hidden_at',
        'admin_deleted_at',
        'admin_visibility_reason',
        'admin_visibility_changed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'freelancer_last_read_at' => 'datetime',
            'client_last_read_at' => 'datetime',
            'admin_hidden_at' => 'datetime',
            'admin_deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quest, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return HasMany<QuestConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(QuestConversationMessage::class, 'quest_conversation_thread_id')
            ->orderBy('created_at');
    }

    public function adminVisibilityChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_visibility_changed_by');
    }

    public function isBlockedByAdmin(): bool
    {
        return $this->admin_hidden_at !== null || $this->admin_deleted_at !== null;
    }
}
