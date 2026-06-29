<?php

namespace App\Models;

use App\Enums\QuestDisputeManagementStatus;
use App\Enums\QuestDisputePhase;
use App\Enums\QuestDisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class QuestDispute extends Model
{
    protected $fillable = [
        'uuid',
        'quest_id',
        'quest_offer_id',
        'opened_by_user_id',
        'assigned_staff_id',
        'reassignment_count',
        'staff_claimed_at',
        'staff_acknowledged_at',
        'workflow_state',
        'ready_for_decision_at',
        'held_at',
        'hold_reason',
        'reason',
        'structured_intake',
        'phase',
        'status',
        'management_status',
        'severity',
        'tier',
        'appeals_used',
        'disputed_amount_minor',
        'response_required_by',
        'ruling_required_by',
        'escalated_at',
        'resolved_at',
        'management_resolved_at',
        'appeal_window_ends_at',
        'finalized_at',
        'resolution_outcome',
        'final_client_share_percent',
        'ruling_favoured_user_id',
        'super_admin_decided_by',
        'super_admin_decided_at',
        'super_admin_decision_notes',
        'sanction_payload',
        'outcome_action',
        'extended_deadline_at',
        'chargeback_risk_flagged_at',
        'pattern_investigation_at',
        'report_path',
        'report_generated_at',
        'sealed_at',
        'awaiting_user_id',
        'client_agrees_resolve_at',
        'freelancer_agrees_resolve_at',
        'negotiation_phase',
        'client_negotiation_attempts',
        'freelancer_negotiation_attempts',
        'active_negotiation_offer_id',
        'binding_mediation_ack_client_at',
        'binding_mediation_ack_freelancer_at',
        'mutual_agreement_submitted_at',
        'enforcement_pending_at',
        'rejection_window_ends_at',
        'final_binding_at',
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
            'management_status' => QuestDisputeManagementStatus::class,
            'response_required_by' => 'datetime',
            'ruling_required_by' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'management_resolved_at' => 'datetime',
            'appeal_window_ends_at' => 'datetime',
            'finalized_at' => 'datetime',
            'ready_for_decision_at' => 'datetime',
            'super_admin_decided_at' => 'datetime',
            'sanction_payload' => 'array',
            'workflow_state' => 'array',
            'staff_acknowledged_at' => 'datetime',
            'held_at' => 'datetime',
            'extended_deadline_at' => 'datetime',
            'chargeback_risk_flagged_at' => 'datetime',
            'pattern_investigation_at' => 'datetime',
            'report_generated_at' => 'datetime',
            'sealed_at' => 'datetime',
            'client_agrees_resolve_at' => 'datetime',
            'freelancer_agrees_resolve_at' => 'datetime',
            'binding_mediation_ack_client_at' => 'datetime',
            'binding_mediation_ack_freelancer_at' => 'datetime',
            'mutual_agreement_submitted_at' => 'datetime',
            'enforcement_pending_at' => 'datetime',
            'rejection_window_ends_at' => 'datetime',
            'final_binding_at' => 'datetime',
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

    public function superAdminDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_decided_by');
    }

    /**
     * @return HasMany<DisputeAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(DisputeAssessment::class)->orderByDesc('id');
    }

    public function mediationSessions(): HasMany
    {
        return $this->hasMany(DisputeMediationSession::class)->orderByDesc('id');
    }

    public function precedents(): HasMany
    {
        return $this->hasMany(DisputePrecedent::class)->orderByDesc('id');
    }

    public function latestSubmittedAssessment(): ?DisputeAssessment
    {
        return $this->assessments()->where('status', 'submitted')->first();
    }

    public function displayReference(): string
    {
        $compact = strtoupper(substr(str_replace('-', '', (string) $this->uuid), 0, 8));

        return 'DSP-'.$compact;
    }

    public function isManagementActive(): bool
    {
        return ! in_array($this->management_status, [
            QuestDisputeManagementStatus::Finalized,
        ], true);
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
     * @return HasMany<DisputeResolutionRequest, $this>
     */
    public function resolutionRequests(): HasMany
    {
        return $this->hasMany(DisputeResolutionRequest::class);
    }

    public function negotiationOffers(): HasMany
    {
        return $this->hasMany(DisputeNegotiationOffer::class)->orderBy('id');
    }

    public function activeNegotiationOffer(): BelongsTo
    {
        return $this->belongsTo(DisputeNegotiationOffer::class, 'active_negotiation_offer_id');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(DisputeAppeal::class)->orderByDesc('id');
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

    /**
     * Whether this dispute still blocks opening another on the same contract.
     */
    public function isActiveOnContract(): bool
    {
        if ($this->management_status === QuestDisputeManagementStatus::Finalized) {
            return false;
        }

        if ($this->finalized_at !== null) {
            return false;
        }

        if ($this->status === QuestDisputeStatus::ClosedWithdrawn) {
            return false;
        }

        return true;
    }

    /**
     * @return HasOne<QuestContract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(QuestContract::class, 'quest_offer_id', 'quest_offer_id');
    }
}
