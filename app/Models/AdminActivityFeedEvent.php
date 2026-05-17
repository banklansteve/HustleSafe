<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdminActivityFeedEvent extends Model
{
    protected $fillable = [
        'uuid',
        'category',
        'event_key',
        'severity',
        'title',
        'summary',
        'entities',
        'metadata',
        'amount_minor',
        'actor_user_id',
        'subject_type',
        'subject_id',
        'state_id',
        'local_government_id',
        'quest_category_id',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdminActivityFeedEvent $event): void {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
            if (empty($event->occurred_at)) {
                $event->occurred_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'entities' => 'array',
            'metadata' => 'array',
            'amount_minor' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function localGovernment(): BelongsTo
    {
        return $this->belongsTo(LocalGovernment::class);
    }

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(QuestCategory::class, 'quest_category_id');
    }
}
