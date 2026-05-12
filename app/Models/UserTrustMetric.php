<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTrustMetric extends Model
{
    protected $fillable = [
        'user_id',
        'freelancer_trust_score',
        'client_trust_score',
        'profile_completion_percent',
        'avg_rating_as_freelancer',
        'avg_rating_as_client',
        'ratings_count_as_freelancer',
        'ratings_count_as_client',
        'last_recomputed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_recomputed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
