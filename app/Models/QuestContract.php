<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestContract extends Model
{
    protected $fillable = [
        'reference_code',
        'quest_id',
        'quest_offer_id',
        'client_id',
        'freelancer_id',
        'status',
        'generated_at',
        'escrow_expires_at',
        'activated_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'escrow_funding_reference',
        'escrow_funded_at',
        'contract_start_date',
        'agreed_delivery_date',
        'revisions_included',
        'revisions_used',
        'amendment_count',
        'parties_snapshot',
        'quest_snapshot',
        'financial_snapshot',
        'timeline_snapshot',
        'revision_policy_snapshot',
        'platform_terms_snapshot',
        'signatures_snapshot',
        'current_terms_snapshot',
        'active_dispute_id',
        'flagged_for_review',
        'flagged_for_review_reason',
        'flagged_for_review_by',
        'flagged_for_review_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'generated_at' => 'datetime',
            'escrow_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'escrow_funded_at' => 'datetime',
            'contract_start_date' => 'date',
            'agreed_delivery_date' => 'date',
            'parties_snapshot' => 'array',
            'quest_snapshot' => 'array',
            'financial_snapshot' => 'array',
            'timeline_snapshot' => 'array',
            'revision_policy_snapshot' => 'array',
            'platform_terms_snapshot' => 'array',
            'signatures_snapshot' => 'array',
            'current_terms_snapshot' => 'array',
            'flagged_for_review' => 'boolean',
            'flagged_for_review_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference_code';
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(QuestOffer::class, 'quest_offer_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function activeDispute(): BelongsTo
    {
        return $this->belongsTo(QuestDispute::class, 'active_dispute_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(QuestContractDeliverable::class)->orderBy('position');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(QuestContractMilestone::class)->orderBy('position');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(QuestContractAmendment::class)->orderBy('amendment_number');
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuestContractEvent::class)->orderByDesc('created_at');
    }

    public function isParty(User $user): bool
    {
        return in_array((int) $user->id, [(int) $this->client_id, (int) $this->freelancer_id], true);
    }

    public function effectiveTerms(): array
    {
        return $this->current_terms_snapshot ?? [
            'financial' => $this->financial_snapshot,
            'timeline' => $this->timeline_snapshot,
            'quest' => $this->quest_snapshot,
            'revision_policy' => $this->revision_policy_snapshot,
        ];
    }
}
