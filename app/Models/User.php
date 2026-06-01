<?php

namespace App\Models;

use App\Mail\WelcomeVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'slug',
        'uid',
        'referral_code',
        'referred_by_user_id',
        'referral_program_blocked_at',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'nin',
        'bvn',
        'gender',
        'date_of_birth',
        'company_name',
        'address_line',
        'city',
        'state_id',
        'local_government_id',
        'latitude',
        'longitude',
        'geocoded_at',
        'account_type',
        'role_id',
        'profession',
        'bio',
        'headline',
        'hourly_rate_min',
        'hourly_rate_max',
        'years_experience',
        'availability',
        'power_hours',
        'verification_tier',
        'kyc_tier',
        'current_verification_level',
        'verification_level_override',
        'verification_level_override_reason',
        'verification_level_overridden_by',
        'verification_level_overridden_at',
        'custom_client_post_limit_minor',
        'custom_freelancer_proposal_limit_minor',
        'verification_restricted_at',
        'verification_restriction_reason',
        'kyc_status',
        'kyc_verified_at',
        'response_time_hours',
        'job_title',
        'company_size',
        'timezone',
        'locale',
        'onboarding_step',
        'last_active_at',
        'freelancer_last_setup_reminder_at',
        'suspended_at',
        'under_review_at',
        'banned_at',
        'ban_reason',
        'google_id',
        'avatar_url',
        'public_profile_settings',
        'hide_online_presence',
        'password',
        'operations_staff_invited_at',
        'operations_staff_invited_by',
        'operations_staff_password_set_at',
    ];

    protected $with = [
        'role',
        'trustMetrics',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'trust_score',
        'client_trust_score',
        'profile_completion_percent',
        'avg_rating_as_freelancer',
        'avg_rating_as_client',
        'ratings_count_as_freelancer',
        'ratings_count_as_client',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'referral_program_blocked_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'hourly_rate_min' => 'decimal:2',
            'hourly_rate_max' => 'decimal:2',
            'last_active_at' => 'datetime',
            'freelancer_last_setup_reminder_at' => 'datetime',
            'suspended_at' => 'datetime',
            'under_review_at' => 'datetime',
            'banned_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'operations_staff_invited_at' => 'datetime',
            'operations_staff_password_set_at' => 'datetime',
            'geocoded_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'verification_level_overridden_at' => 'datetime',
            'verification_restricted_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'public_profile_settings' => 'array',
            'power_hours' => 'array',
            'hide_online_presence' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Super admin who invited this operations staff member (if any).
     *
     * @return BelongsTo<User, $this>
     */
    public function operationsStaffInvitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operations_staff_invited_by');
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
    public function localGovernmentModel(): BelongsTo
    {
        return $this->belongsTo(LocalGovernment::class, 'local_government_id');
    }

    /**
     * @return HasOne<UserTrustMetric, $this>
     */
    public function trustMetrics(): HasOne
    {
        return $this->hasOne(UserTrustMetric::class);
    }

    /**
     * @return HasMany<UserVerification, $this>
     */
    public function userVerifications(): HasMany
    {
        return $this->hasMany(UserVerification::class);
    }

    public function kycReviewCases(): HasMany
    {
        return $this->hasMany(KycReviewCase::class);
    }

    public function verificationAnomalyFlags(): HasMany
    {
        return $this->hasMany(VerificationAnomalyFlag::class);
    }

    /**
     * @return HasMany<AdminUserNote, $this>
     */
    public function adminNotes(): HasMany
    {
        return $this->hasMany(AdminUserNote::class);
    }

    /**
     * @return BelongsToMany<AdminUserTag, $this>
     */
    public function adminTags(): BelongsToMany
    {
        return $this->belongsToMany(AdminUserTag::class, 'admin_user_tag_user')->withTimestamps();
    }

    /**
     * @return BelongsToMany<AdminUserBadge, $this>
     */
    public function adminBadges(): BelongsToMany
    {
        return $this->belongsToMany(AdminUserBadge::class, 'admin_user_badge_user')->withTimestamps();
    }

    public function promotionBadges(): BelongsToMany
    {
        return $this->belongsToMany(PromotionBadge::class, 'promotion_badge_user')
            ->withPivot(['awarded_by_admin_id', 'justification', 'awarded_at', 'expires_at', 'revoked_at'])
            ->wherePivotNull('revoked_at')
            ->withTimestamps();
    }

    public function referralsMade(): HasMany
    {
        return $this->hasMany(UserReferral::class, 'referrer_user_id');
    }

    public function referralReceived(): HasMany
    {
        return $this->hasMany(UserReferral::class, 'referred_user_id');
    }

    /**
     * @return HasMany<AdminUserSanction, $this>
     */
    public function sanctions(): HasMany
    {
        return $this->hasMany(AdminUserSanction::class);
    }

    /**
     * @return HasOne<FreelancerBusinessProfile, $this>
     */
    public function freelancerBusinessProfile(): HasOne
    {
        return $this->hasOne(FreelancerBusinessProfile::class);
    }

    /**
     * @return HasMany<FreelancerCredential, $this>
     */
    public function freelancerCredentials(): HasMany
    {
        return $this->hasMany(FreelancerCredential::class)->orderBy('display_order')->orderBy('id');
    }

    /**
     * @return HasMany<Portfolio, $this>
     */
    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    /**
     * @return HasOne<OnboardingQualityReview, $this>
     */
    public function onboardingQualityReview(): HasOne
    {
        return $this->hasOne(OnboardingQualityReview::class);
    }

    /**
     * @return HasMany<LoginEvent, $this>
     */
    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    /**
     * Portfolios this user has favourited.
     *
     * @return BelongsToMany<Portfolio, $this>
     */
    public function favoritedPortfolios(): BelongsToMany
    {
        return $this->belongsToMany(Portfolio::class, 'portfolio_favorites')->withTimestamps();
    }

    /**
     * Users following this user (this user is the talent).
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'following_id', 'follower_id')->withTimestamps();
    }

    /**
     * Users this user follows.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followingUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'following_id')->withTimestamps();
    }

    /**
     * @return array<string, bool>
     */
    public function effectivePublicProfileSettings(): array
    {
        /** @var array<string, bool> $defaults */
        $defaults = match ($this->role?->slug) {
            'freelancer' => config('profile.public_defaults', []),
            default => config('profile.client_public_defaults', config('profile.public_defaults', [])),
        };

        $merged = array_merge($defaults, $this->public_profile_settings ?? []);
        unset($merged['show_phone'], $merged['show_email']);

        return $merged;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * @return HasMany<Quest, $this>
     */
    public function questsAsClient(): HasMany
    {
        return $this->hasMany(Quest::class, 'client_id');
    }

    /**
     * @return HasMany<Quest, $this>
     */
    public function questsAsFreelancer(): HasMany
    {
        return $this->hasMany(Quest::class, 'freelancer_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviewsWritten(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    /**
     * Quest subcategories this freelancer wants to work in (leaf categories only).
     *
     * @return BelongsToMany<QuestCategory, $this>
     */
    public function questCategoryPreferences(): BelongsToMany
    {
        return $this->belongsToMany(QuestCategory::class, 'freelancer_quest_category')->withTimestamps();
    }

    /**
     * @return HasMany<QuestOffer, $this>
     */
    public function questOffers(): HasMany
    {
        return $this->hasMany(QuestOffer::class, 'freelancer_id');
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            UserTrustMetric::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'freelancer_trust_score' => 0,
                    'client_trust_score' => 50,
                    'profile_completion_percent' => 0,
                ]
            );
        });

        static::creating(function (User $user): void {
            if (empty($user->uid)) {
                $user->uid = static::generateUniqueUid();
            }

            if (empty($user->referral_code)) {
                $user->referral_code = static::generateReferralCode();
            }

            if (empty($user->username) && ! empty($user->email)) {
                $user->username = static::generateUniqueUsername((string) $user->email);
            }

            if (empty($user->slug)) {
                $base = $user->name ?? (string) $user->email;
                $user->slug = static::generateUniqueSlug($base);
            }

            if ($user->role_id === null && $user->account_type !== null) {
                $roleSlug = match ($user->account_type) {
                    'admin' => 'admin',
                    'freelancer', 'hustler' => 'freelancer',
                    'client', 'sponsor' => 'client',
                    default => 'client',
                };
                $user->role_id = Role::query()->where('slug', $roleSlug)->value('id');
            }

            if ($user->timezone === null) {
                $user->timezone = 'Africa/Lagos';
            }

            if ($user->locale === null) {
                $user->locale = 'en';
            }
        });
    }

    public static function generateUniqueUid(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $uid = '';
            for ($i = 0; $i < 8; $i++) {
                $uid .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (static::query()->where('uid', $uid)->exists());

        return $uid;
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::query()->where('referral_code', $code)->exists());

        return $code;
    }

    public static function generateUniqueUsername(string $email): string
    {
        $local = Str::before($email, '@');
        $base = Str::slug($local, '');
        $base = $base !== '' ? Str::limit($base, 60, '') : 'user';
        $candidate = $base;
        $i = 0;

        while (static::query()->where('username', $candidate)->exists()) {
            $candidate = Str::limit($base, 52, '').($i++);
        }

        return $candidate;
    }

    public static function generateUniqueSlug(string $from): string
    {
        $base = Str::slug($from) ?: 'profile';
        $candidate = $base;
        $i = 0;

        while (static::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.Str::lower(Str::random(4));
            if ($i++ > 40) {
                $candidate = $base.'-'.Str::lower(Str::random(8));
                break;
            }
        }

        return $candidate;
    }

    /**
     * Send a single welcome email that includes the email verification link.
     */
    public function sendEmailVerificationNotification(): void
    {
        Mail::to($this->getEmailForVerification())->send(new WelcomeVerifyEmail($this));
    }

    protected function trustScore(): Attribute
    {
        return Attribute::get(fn () => (int) ($this->trustMetrics?->freelancer_trust_score ?? 0));
    }

    protected function clientTrustScore(): Attribute
    {
        return Attribute::get(fn () => (int) ($this->trustMetrics?->client_trust_score ?? 0));
    }

    protected function profileCompletionPercent(): Attribute
    {
        return Attribute::get(fn () => (int) ($this->trustMetrics?->profile_completion_percent ?? 0));
    }

    protected function avgRatingAsFreelancer(): Attribute
    {
        return Attribute::get(fn () => $this->trustMetrics?->avg_rating_as_freelancer);
    }

    protected function avgRatingAsClient(): Attribute
    {
        return Attribute::get(fn () => $this->trustMetrics?->avg_rating_as_client);
    }

    protected function ratingsCountAsFreelancer(): Attribute
    {
        return Attribute::get(fn () => (int) ($this->trustMetrics?->ratings_count_as_freelancer ?? 0));
    }

    protected function ratingsCountAsClient(): Attribute
    {
        return Attribute::get(fn () => (int) ($this->trustMetrics?->ratings_count_as_client ?? 0));
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function walletBankAccounts(): HasMany
    {
        return $this->hasMany(WalletBankAccount::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
