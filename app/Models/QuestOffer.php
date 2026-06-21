<?php

namespace App\Models;

use App\Enums\AdminProposalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class QuestOffer extends Model
{
    protected static function booted(): void
    {
        static::creating(function (QuestOffer $offer): void {
            if (empty($offer->uuid)) {
                $offer->uuid = (string) Str::uuid();
            }
            if (empty($offer->reference_code)) {
                $quest = $offer->relationLoaded('quest')
                    ? $offer->quest
                    : Quest::query()->find($offer->quest_id);

                if ($quest !== null) {
                    $offer->reference_code = app(\App\Services\Proposals\ProposalReferenceGenerator::class)
                        ->nextForQuest($quest);
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getRouteKey(): mixed
    {
        return $this->uuid;
    }

    /**
     * Resolve by UUID (canonical). Numeric IDs still resolve for legacy bookmarks.
     *
     * @param  mixed  $value
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return static::query()->where($field, $value)->firstOrFail();
        }

        return static::query()
            ->where(function ($q) use ($value): void {
                $q->where('uuid', $value);

                if (is_string($value) && str_contains($value, '-')) {
                    $normalized = \App\Support\References\HustleSafeReferenceAlphabet::normalize($value);
                    $q->orWhere('reference_code', $normalized);
                }

                if (is_numeric($value) && (int) $value > 0) {
                    $q->orWhere('id', (int) $value);
                }
            })
            ->firstOrFail();
    }

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
        'accepts_installment_terms',
        'progress_report_frequency',
        'progress_report_frequency_note',
        'materials',
        'pricing_snapshot',
        'quoted_amount_minor',
        'accepted_at',
        'declined_at',
        'withdrawn_at',
        'shortlisted_at',
        'award_client_confirmed_at',
        'award_freelancer_confirmed_at',
        'award_terms_snapshot',
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
            'accepts_installment_terms' => 'boolean',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'shortlisted_at' => 'datetime',
            'award_client_confirmed_at' => 'datetime',
            'award_freelancer_confirmed_at' => 'datetime',
            'award_terms_snapshot' => 'array',
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
     * @return HasMany<ProposalPreferenceResponse, $this>
     */
    public function preferenceResponses(): HasMany
    {
        return $this->hasMany(ProposalPreferenceResponse::class);
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

    public function clarificationThread(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProposalClarificationThread::class, 'quest_offer_id');
    }

    public function isPendingAward(): bool
    {
        return $this->status === 'pending_award';
    }

    public function isAwardMutuallyConfirmed(): bool
    {
        return $this->award_client_confirmed_at !== null && $this->award_freelancer_confirmed_at !== null;
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
            ->whereIn('status', ['submitted', 'shortlisted', 'pending_award', 'accepted']);
    }

    /**
     * @param  list<int>  $questIds
     * @return array<int, self>
     */
    public static function mapForFreelancerOnQuests(int $freelancerId, array $questIds): array
    {
        if ($questIds === []) {
            return [];
        }

        $offers = self::query()
            ->where('freelancer_id', $freelancerId)
            ->whereIn('quest_id', $questIds)
            ->excludingAdminSuspended()
            ->orderByDesc('id')
            ->get();

        $map = [];
        foreach ($offers->groupBy(fn (self $offer) => (int) $offer->quest_id) as $questId => $group) {
            $picked = self::pickFreelancerOffer($group);
            if ($picked !== null) {
                $map[(int) $questId] = $picked;
            }
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, self>|null  $offers
     */
    public static function pickFreelancerOffer(?\Illuminate\Support\Collection $offers): ?self
    {
        if ($offers === null || $offers->isEmpty()) {
            return null;
        }

        $active = $offers->first(
            fn (self $offer) => in_array($offer->status, ['submitted', 'shortlisted', 'accepted', 'pending_award'], true),
        );

        return $active ?? $offers->first();
    }
}
