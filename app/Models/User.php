<?php

namespace App\Models;

use App\Mail\WelcomeVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'gender',
        'date_of_birth',
        'company_name',
        'address_line',
        'latitude',
        'longitude',
        'geocoded_at',
        'local_government',
        'state',
        'account_type',
        'role_id',
        'profession',
        'bio',
        'headline',
        'hourly_rate_min',
        'hourly_rate_max',
        'years_experience',
        'availability',
        'verification_tier',
        'trust_score',
        'response_time_hours',
        'job_title',
        'company_size',
        'timezone',
        'locale',
        'onboarding_step',
        'last_active_at',
        'suspended_at',
        'google_id',
        'avatar_url',
        'password',
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
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'hourly_rate_min' => 'decimal:2',
            'hourly_rate_max' => 'decimal:2',
            'last_active_at' => 'datetime',
            'suspended_at' => 'datetime',
            'geocoded_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->uid)) {
                $user->uid = static::generateUniqueUid();
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
                    'hustler' => 'freelancer',
                    'sponsor' => 'client',
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
}
