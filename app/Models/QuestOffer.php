<?php

namespace App\Models;

use App\Enums\AdminProposalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class QuestOffer extends Model
{
    protected $fillable = [
        'quest_id',
        'freelancer_id',
        'status',
        'admin_status',
        'admin_status_reason',
        'admin_status_changed_by',
        'admin_status_changed_at',
        'admin_notice_severity',
        'pitch',
        'scope_detail',
        'warranty_terms',
        'proposed_completion_date',
        'planned_start_date',
        'planned_finish_date',
        'estimated_duration_days',
        'corrections_included',
        'corrections_rounds',
        'progress_report_frequency',
        'materials',
        'pricing_snapshot',
        'quoted_amount_minor',
        'accepted_at',
        'declined_at',
        'withdrawn_at',
        'shortlisted_at',
        'client_pinned_at',
        'client_view_count',
        'last_client_view_at',
        'freelancer_edit_deadline_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admin_status' => AdminProposalStatus::class,
            'admin_status_changed_at' => 'datetime',
            'materials' => 'array',
            'pricing_snapshot' => 'array',
            'proposed_completion_date' => 'date',
            'planned_start_date' => 'date',
            'planned_finish_date' => 'date',
            'corrections_included' => 'boolean',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'shortlisted_at' => 'datetime',
            'client_pinned_at' => 'datetime',
            'last_client_view_at' => 'datetime',
            'freelancer_edit_deadline_at' => 'datetime',
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

    public function adminStatusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_status_changed_by');
    }

    public function adminProposalFlags(): HasMany
    {
        return $this->hasMany(AdminProposalFlag::class, 'quest_offer_id');
    }

    public function activeAdminProposalFlags(): HasMany
    {
        return $this->hasMany(AdminProposalFlag::class, 'quest_offer_id')->where('status', 'open');
    }

    public function adminProposalNotices(): HasMany
    {
        return $this->hasMany(AdminProposalNotice::class, 'quest_offer_id');
    }

    public function visibleAdminProposalNotices(): HasMany
    {
        return $this->hasMany(AdminProposalNotice::class, 'quest_offer_id')
            ->where(function ($query): void {
                $query->where('visible_to_freelancer', true)->orWhere('visible_to_client', true);
            })
            ->latest();
    }

    public function adminProposalNotes(): HasMany
    {
        return $this->hasMany(AdminProposalNote::class, 'quest_offer_id');
    }

    /**
     * @param  Builder<QuestOffer>  $query
     */
    public function scopeExcludingAdminSuspended(Builder $query): Builder
    {
        if (! Schema::hasColumn('quest_offers', 'admin_status')) {
            return $query;
        }

        return $query->where(function (Builder $inner): void {
            $inner->whereNull('admin_status')
                ->orWhere('admin_status', '<>', AdminProposalStatus::Suspended->value);
        });
    }

    /**
     * Proposals the client should see in inbox / quest detail.
     *
     * @param  Builder<QuestOffer>  $query
     */
    public function scopeVisibleInClientInbox(Builder $query): Builder
    {
        return $query
            ->excludingAdminSuspended()
            ->whereIn('status', ['submitted', 'shortlisted', 'accepted']);
    }
}
