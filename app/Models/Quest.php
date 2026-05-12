<?php

namespace App\Models;

use App\Enums\QuestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Quest extends Model
{
    protected $fillable = [
        'uuid',
        'client_id',
        'freelancer_id',
        'title',
        'description',
        'quest_category_id',
        'state_id',
        'city',
        'latitude',
        'longitude',
        'status',
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
        });
    }

    protected function casts(): array
    {
        return [
            'status' => QuestStatus::class,
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
