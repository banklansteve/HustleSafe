<?php

namespace App\Models;

use App\Enums\QuestAvailabilityNeed;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestProjectType;
use App\Enums\QuestPromotionTier;
use App\Enums\QuestStartTiming;
use App\Enums\QuestStatus;
use App\Enums\QuestTeamSize;
use App\Enums\QuestVisibility;
use App\Enums\AdminQuestStatus;
use App\Models\QuestBoost;
use App\Services\Quest\QuestCategoryReferenceCodeService;
use App\Services\QuestFileStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Quest extends Model
{
    protected $fillable = [
        'uuid',
        'reference_code',
        'slug',
        'client_id',
        'freelancer_id',
        'title',
        'description',
        'quality_gate_feedback',
        'quality_gate_failed_at',
        'health_score',
        'health_score_updated_at',
        'quest_category_id',
        'state_id',
        'local_government_id',
        'city',
        'latitude',
        'longitude',
        'status',
        'admin_status',
        'admin_status_reason',
        'admin_status_changed_by',
        'admin_status_changed_at',
        'escrow_status',
        'accepted_quest_offer_id',
        'pending_award_offer_id',
        'visibility',
        'freelancer_location_pref',
        'availability_need',
        'project_type',
        'estimated_hours',
        'team_size',
        'auto_listing_expiry_days',
        'listing_expires_at',
        'client_edit_until',
        'max_offers',
        'views_count',
        'offers_count',
        'saves_count',
        'traffic_source',
        'traffic_utm',
        'terms_accepted_at',
        'start_timing',
        'estimated_completion_days',
        'estimated_delivery_date',
        'site_visits_allowed',
        'site_access_level',
        'pets_on_site',
        'pets_detail',
        'scheduled_start_date',
        'budget_amount_minor',
        'paid_out_minor',
        'refunded_minor',
        'due_at',
        'escrow_funded_at',
        'delivery_acknowledged_at',
        'delivery_acknowledged_by',
        'release_authorized_at',
        'release_authorized_by',
        'release_hold_until',
        'release_hold_reason',
        'release_hold_by',
        'escrow_held_at',
        'escrow_hold_reason',
        'escrow_hold_expected_resolution_at',
        'escrow_frozen_at',
        'escrow_freeze_reason',
        'delivered_at',
        'completed_at',
        'funds_released_at',
        'auto_completed_at',
        'completed_on_time',
        'dispute_opened',
        'closure_type',
        'listing_extension_count',
        'listing_extended_at',
        'listing_extension_reason',
        'listing_expiry_warning_sent_at',
        'reposted_from_quest_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Quest $quest): void {
            if (empty($quest->uuid)) {
                $quest->uuid = (string) Str::uuid();
            }
            if (empty($quest->reference_code)) {
                $quest->reference_code = static::generateReferenceCode(
                    $quest->quest_category_id ? (int) $quest->quest_category_id : null
                );
            }
        });

        static::deleting(function (Quest $quest): void {
            $quest->loadMissing('files');
            $storage = app(QuestFileStorageService::class);
            foreach ($quest->files as $file) {
                $storage->purgeBinary($file);
            }
        });
    }

    /**
     * Cover image URL when set; otherwise the configured default asset.
     */
    public function displayCoverUrl(): string
    {
        $url = $this->cover_image_url;
        if (is_string($url) && $url !== '') {
            return $url;
        }

        return asset(config('quests.default_cover_asset', 'images/quest-cover-default.svg'));
    }

    public static function generateReferenceCode(?int $questCategoryId = null): string
    {
        $prefix = app(QuestCategoryReferenceCodeService::class)->prefixForCategoryId($questCategoryId);
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        do {
            $suffix = '';
            for ($i = 0; $i < 7; $i++) {
                $suffix .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $code = $prefix.'-'.$suffix;
        } while (static::query()->where('reference_code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Prefer SEO-friendly slug; fall back to UUID for legacy rows.
     */
    public function getRouteKey(): mixed
    {
        $slug = $this->slug;

        return ($slug !== null && $slug !== '') ? $slug : $this->uuid;
    }

    /**
     * Resolve by slug (canonical) or UUID (legacy bookmarks).
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
                $q->where('slug', $value);
                if (is_string($value) && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value)) {
                    $q->orWhere('uuid', $value);
                }
                if (is_numeric($value) && (int) $value > 0) {
                    $q->orWhere('id', (int) $value);
                }
            })
            ->firstOrFail();
    }

    protected function casts(): array
    {
        return [
            'status' => QuestStatus::class,
            'admin_status' => AdminQuestStatus::class,
            'visibility' => QuestVisibility::class,
            'freelancer_location_pref' => QuestFreelancerLocationPref::class,
            'availability_need' => QuestAvailabilityNeed::class,
            'project_type' => QuestProjectType::class,
            'team_size' => QuestTeamSize::class,
            'promotion_tier' => QuestPromotionTier::class,
            'traffic_utm' => 'array',
            'quality_gate_feedback' => 'array',
            'quality_gate_failed_at' => 'datetime',
            'health_score_updated_at' => 'datetime',
            'start_timing' => QuestStartTiming::class,
            'site_visits_allowed' => 'boolean',
            'pets_on_site' => 'boolean',
            'scheduled_start_date' => 'date',
            'estimated_delivery_date' => 'date',
            'listing_expires_at' => 'datetime',
            'listing_extended_at' => 'datetime',
            'listing_expiry_warning_sent_at' => 'datetime',
            'client_edit_until' => 'datetime',
            'admin_status_changed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'due_at' => 'datetime',
            'escrow_funded_at' => 'datetime',
            'delivery_acknowledged_at' => 'datetime',
            'release_authorized_at' => 'datetime',
            'release_hold_until' => 'datetime',
            'funds_released_at' => 'datetime',
            'escrow_held_at' => 'datetime',
            'escrow_hold_expected_resolution_at' => 'datetime',
            'escrow_frozen_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'completed_on_time' => 'boolean',
            'dispute_opened' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * @return BelongsTo<QuestOffer, $this>
     */
    public function acceptedOffer(): BelongsTo
    {
        return $this->belongsTo(QuestOffer::class, 'accepted_quest_offer_id');
    }

    /**
     * @return BelongsTo<QuestOffer, $this>
     */
    public function pendingAwardOffer(): BelongsTo
    {
        return $this->belongsTo(QuestOffer::class, 'pending_award_offer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<QuestOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(QuestOffer::class);
    }

    /**
     * @return HasMany<QuestNudgeLog, $this>
     */
    public function nudgeLogs(): HasMany
    {
        return $this->hasMany(QuestNudgeLog::class)->latest('sent_at');
    }

    /**
     * @return HasMany<QuestListingExtensionLog, $this>
     */
    public function listingExtensionLogs(): HasMany
    {
        return $this->hasMany(QuestListingExtensionLog::class)->latest('id');
    }

    /**
     * @return HasMany<QuestConversationThread, $this>
     */
    public function conversationThreads(): HasMany
    {
        return $this->hasMany(QuestConversationThread::class);
    }

    /**
     * @return HasMany<QuestFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(QuestFile::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<QuestBookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(QuestBookmark::class);
    }

    /**
     * @return BelongsTo<QuestCategory, $this>
     */
    public function questCategory(): BelongsTo
    {
        return $this->belongsTo(QuestCategory::class, 'quest_category_id');
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function stateModel(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    /**
     * @return BelongsTo<LocalGovernment, $this>
     */
    public function localGovernment(): BelongsTo
    {
        return $this->belongsTo(LocalGovernment::class, 'local_government_id');
    }

    /**
     * @return HasMany<QuestDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(QuestDispute::class);
    }

    /**
     * @return HasMany<QuestLifecycleEmailLog, $this>
     */
    public function lifecycleEmailLogs(): HasMany
    {
        return $this->hasMany(QuestLifecycleEmailLog::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<PaymentEscrow, $this>
     */
    public function paymentEscrow(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PaymentEscrow::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\QuestContract, $this>
     */
    public function contract(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\QuestContract::class);
    }

    /**
     * @return HasMany<AdminFinancialLedgerEntry, $this>
     */
    public function adminFinancialLedgerEntries(): HasMany
    {
        return $this->hasMany(AdminFinancialLedgerEntry::class)->orderBy('occurred_at');
    }

    public function featuredListings(): HasMany
    {
        return $this->hasMany(FeaturedQuestListing::class);
    }

    public function activeFeaturedListing(): HasMany
    {
        return $this->hasMany(FeaturedQuestListing::class)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now());
    }

    public function questBoosts(): HasMany
    {
        return $this->hasMany(QuestBoost::class);
    }

    public function activeQuestBoost(): HasMany
    {
        return $this->hasMany(QuestBoost::class)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function adminQuestFlags(): HasMany
    {
        return $this->hasMany(AdminQuestFlag::class);
    }

    public function activeAdminQuestFlags(): HasMany
    {
        return $this->hasMany(AdminQuestFlag::class)->where('status', 'open');
    }

    public function adminStatusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_status_changed_by');
    }

    public function adminQuestNotices(): HasMany
    {
        return $this->hasMany(AdminQuestNotice::class);
    }

    public function visibleAdminQuestNotices(): HasMany
    {
        return $this->hasMany(AdminQuestNotice::class)->where('visible_to_users', true)->latest();
    }

    public function adminQuestNotes(): HasMany
    {
        return $this->hasMany(AdminQuestNote::class);
    }

    public function isAdminSuspended(): bool
    {
        return ($this->admin_status?->value ?? (string) $this->admin_status) === AdminQuestStatus::Suspended->value;
    }

    public function isAdminRestricted(): bool
    {
        return in_array($this->admin_status?->value ?? (string) $this->admin_status, [
            AdminQuestStatus::Restricted->value,
            AdminQuestStatus::Suspended->value,
        ], true);
    }

    /**
     * Best-effort anchor for engagement / reminder emails (due date, delivery date, or planned finish).
     */
    public function expectedCompletionAnchor(): ?Carbon
    {
        return app(\App\Services\QuestEngagementLifecycleService::class)->expectedCompletionAt($this);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function invitedFreelancers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quest_freelancer_invites', 'quest_id', 'freelancer_id')
            ->withTimestamps();
    }

    public function isInvitedFreelancer(User $user): bool
    {
        return $this->invitedFreelancers()->whereKey($user->id)->exists();
    }

    public function isParty(User $user): bool
    {
        return $user->id === $this->client_id || $user->id === $this->freelancer_id;
    }

    public function oppositeParty(User $user): ?User
    {
        if ($user->id === $this->client_id) {
            return $this->freelancer;
        }
        if ($this->freelancer_id !== null && $user->id === $this->freelancer_id) {
            return $this->client;
        }

        return null;
    }
}
