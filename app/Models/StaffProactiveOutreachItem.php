<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StaffProactiveOutreachItem extends Model
{
    protected $fillable = [
        'uuid',
        'situation_key',
        'status',
        'priority',
        'priority_score',
        'target_user_id',
        'quest_id',
        'quest_offer_id',
        'quest_dispute_id',
        'conversation_thread_review_id',
        'fingerprint',
        'context',
        'suggested_template_slug',
        'assigned_staff_id',
        'snoozed_until',
        'detected_at',
        'last_outreach_at',
        'resolved_at',
        'resolution_note',
    ];

    protected static function booted(): void
    {
        static::creating(function (StaffProactiveOutreachItem $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'snoozed_until' => 'datetime',
            'detected_at' => 'datetime',
            'last_outreach_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return BelongsTo<Quest, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /**
     * @return BelongsTo<QuestOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(QuestOffer::class, 'quest_offer_id');
    }

    /**
     * @return BelongsTo<QuestDispute, $this>
     */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(QuestDispute::class, 'quest_dispute_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /**
     * @return HasMany<StaffProactiveOutreachLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(StaffProactiveOutreachLog::class, 'outreach_item_id')->latest('sent_at');
    }
}
