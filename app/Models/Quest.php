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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'quest_category_id',
        'state_id',
        'local_government_id',
        'city',
        'latitude',
        'longitude',
        'status',
        'visibility',
        'freelancer_location_pref',
        'availability_need',
        'project_type',
        'estimated_hours',
        'team_size',
        'promotion_tier',
        'auto_listing_expiry_days',
        'listing_expires_at',
        'max_offers',
        'views_count',
        'offers_count',
        'saves_count',
        'traffic_source',
        'traffic_utm',
        'start_timing',
        'estimated_completion_days',
        'estimated_delivery_date',
        'site_visits_allowed',
        'scheduled_start_date',
        'budget_amount_minor',
        'paid_out_minor',
        'due_at',
        'delivered_at',
        'completed_at',
        'completed_on_time',
        'dispute_opened',
        'closure_type',
    ];

    protected static function booted(): void
    {
        static::creating(function (Quest $quest): void {
            if (empty($quest->uuid)) {
                $quest->uuid = (string) Str::uuid();
            }
            if (empty($quest->reference_code)) {
                $quest->reference_code = static::generateReferenceCode();
            }
        });
    }

    public static function generateReferenceCode(): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        do {
            $suffix = '';
            for ($i = 0; $i < 7; $i++) {
                $suffix .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $code = 'HSQ-'.$suffix;
        } while (static::query()->where('reference_code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => QuestStatus::class,
            'visibility' => QuestVisibility::class,
            'freelancer_location_pref' => QuestFreelancerLocationPref::class,
            'availability_need' => QuestAvailabilityNeed::class,
            'project_type' => QuestProjectType::class,
            'team_size' => QuestTeamSize::class,
            'promotion_tier' => QuestPromotionTier::class,
            'traffic_utm' => 'array',
            'start_timing' => QuestStartTiming::class,
            'site_visits_allowed' => 'boolean',
            'scheduled_start_date' => 'date',
            'estimated_delivery_date' => 'date',
            'listing_expires_at' => 'datetime',
            'due_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'completed_on_time' => 'boolean',
            'dispute_opened' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
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
