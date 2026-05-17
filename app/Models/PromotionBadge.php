<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PromotionBadge extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'criteria',
        'is_automatic',
        'requires_manual_review',
        'is_public',
        'is_time_limited',
        'display_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'is_automatic' => 'boolean',
            'requires_manual_review' => 'boolean',
            'is_public' => 'boolean',
            'is_time_limited' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'promotion_badge_user')
            ->withPivot(['awarded_by_admin_id', 'justification', 'awarded_at', 'expires_at', 'revoked_at'])
            ->withTimestamps();
    }
}
