<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestCategory extends Model
{
    protected $fillable = [
        'parent_id',
        'slug',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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

    public function isLeaf(): bool
    {
        return $this->parent_id !== null;
    }
}
