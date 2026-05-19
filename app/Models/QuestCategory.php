<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class QuestCategory extends Model
{
    protected $fillable = [
        'parent_id',
        'slug',
        'name',
        'description',
        'icon_name',
        'icon_color',
        'sort_order',
        'status',
        'previous_status',
        'is_active',
        'uses_fee_override',
        'client_fee_percent',
        'freelancer_fee_percent',
        'budget_guardrails_enabled',
        'min_budget_minor',
        'max_budget_minor',
        'high_value_approval_enabled',
        'high_value_threshold_minor',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'uses_fee_override' => 'boolean',
            'client_fee_percent' => 'decimal:2',
            'freelancer_fee_percent' => 'decimal:2',
            'budget_guardrails_enabled' => 'boolean',
            'min_budget_minor' => 'integer',
            'max_budget_minor' => 'integer',
            'high_value_approval_enabled' => 'boolean',
            'high_value_threshold_minor' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (QuestCategory $category): void {
            if ($category->status === null || $category->status === '') {
                $category->status = $category->is_active ? 'active' : 'hidden';
            }

            $category->is_active = $category->status === 'active';
            $category->archived_at = $category->status === 'archived'
                ? ($category->archived_at ?? now())
                : null;
        });
    }

    /**
     * @return BelongsTo<QuestCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(QuestCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<QuestCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(QuestCategory::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('status', 'active')->where('is_active', true);
    }

    /**
     * @return HasMany<Quest, $this>
     */
    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class, 'quest_category_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function freelancers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'freelancer_quest_category')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeParents(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubcategories(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeVisibleToUsers(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    public function isLeaf(): bool
    {
        return $this->parent_id !== null;
    }

    public function effectiveClientFeePercent(): ?string
    {
        if ($this->uses_fee_override || $this->parent === null) {
            return $this->client_fee_percent;
        }

        return $this->parent?->client_fee_percent;
    }

    public function effectiveFreelancerFeePercent(): ?string
    {
        if ($this->uses_fee_override || $this->parent === null) {
            return $this->freelancer_fee_percent;
        }

        return $this->parent?->freelancer_fee_percent;
    }
}
