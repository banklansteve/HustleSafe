<?php

namespace App\Models;

use App\Enums\QuestDisputePhase;
use App\Enums\QuestDisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QuestDispute extends Model
{
    protected $fillable = [
        'uuid',
        'quest_id',
        'quest_offer_id',
        'opened_by_user_id',
        'assigned_staff_id',
        'staff_claimed_at',
        'reason',
        'structured_intake',
        'phase',
        'status',
        'tier',
        'appeals_used',
        'disputed_amount_minor',
        'response_required_by',
        'ruling_required_by',
        'escalated_at',
        'resolved_at',
        'resolution_outcome',
        'final_client_share_percent',
        'ruling_favoured_user_id',
        'awaiting_user_id',
        'client_agrees_resolve_at',
        'freelancer_agrees_resolve_at',
        'opening_summary',
    ];

    protected static function booted(): void
    {
        static::creating(function (QuestDispute $d): void {
            if (empty($d->uuid)) {
                $d->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'structured_intake' => 'array',
            'phase' => QuestDisputePhase::class,
            'status' => QuestDisputeStatus::class,
            'response_required_by' => 'datetime',
            'ruling_required_by' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'client_agrees_resolve_at' => 'datetime',
            'freelancer_agrees_resolve_at' => 'datetime',
            'staff_claimed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
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
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rulingFavouredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ruling_favoured_user_id');
    }

    /**
     * @return HasMany<DisputeEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(DisputeEvent::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<DisputeMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<DisputeSettlementOffer, $this>
     */
    public function settlementOffers(): HasMany
    {
        return $this->hasMany(DisputeSettlementOffer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function awaitingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awaiting_user_id');
    }

    public function isParty(User $user): bool
    {
        $this->loadMissing('quest');
        $quest = $this->quest;
        if ($quest === null) {
            return false;
        }

        return $quest->isParty($user);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn], true);
    }
}
