<?php

namespace App\Models;

use App\Enums\PortfolioStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Portfolio extends Model
{
    protected $fillable = [
        'user_id',
        'quest_id',
        'category_id',
        'subcategory_id',
        'title',
        'description',
        'slug',
        'started_at',
        'completed_at',
        'project_cost_minor',
        'cover_path',
        'status',
        'admin_hidden',
        'published_at',
        'favorites_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => PortfolioStatus::class,
            'admin_hidden' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Portfolio $portfolio): void {
            $portfolio->loadMissing('files');
            foreach ($portfolio->files as $file) {
                $file->purgeFromStorage();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function coverUrl(): ?string
    {
        $p = $this->cover_path;
        if ($p === null || $p === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $p)) {
            return $p;
        }

        if (str_starts_with($p, '//')) {
            return 'https:'.$p;
        }

        return Storage::disk('public')->url($p);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Quest, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /**
     * @return BelongsTo<QuestCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<QuestCategory, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(QuestCategory::class, 'subcategory_id');
    }

    /**
     * @return HasMany<PortfolioFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(PortfolioFile::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Users who favourited this portfolio.
     *
     * @return BelongsToMany<User, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'portfolio_favorites')->withTimestamps();
    }

    public function isPublished(): bool
    {
        return $this->status === PortfolioStatus::Published && ! $this->admin_hidden;
    }

    public function isVisibleToPublic(): bool
    {
        return $this->isPublished();
    }
}
