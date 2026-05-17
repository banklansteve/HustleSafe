<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeaturedQuestListing extends Model
{
    protected $fillable = [
        'quest_id',
        'client_user_id',
        'granted_by_admin_id',
        'tier',
        'status',
        'starts_at',
        'expires_at',
        'amount_paid_minor',
        'proposal_views_count',
        'notifications_sent_count',
        'homepage_carousel',
        'weekly_digest',
        'social_post_required',
        'social_post_handled_at',
        'manual_grant_reason',
        'cancelled_at',
        'cancelled_by_admin_id',
        'cancellation_reason',
        'refund_amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'homepage_carousel' => 'boolean',
            'weekly_digest' => 'boolean',
            'social_post_required' => 'boolean',
            'social_post_handled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function grantedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_admin_id');
    }
}
