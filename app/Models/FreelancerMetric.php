<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerMetric extends Model
{
    protected $fillable = [
        'user_id',
        'location_state_id',
        'location_lga_id',
        'typical_job_value_minor',
        'skills_list',
        'completion_rate',
        'average_rating',
        'verification_level',
        'last_proposal_at',
        'dispute_count_last_6_months',
        'cancellation_count_last_6_months',
        'quick_turnaround_completed_count',
        'niche_completions_by_category',
        'refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'skills_list' => 'array',
            'niche_completions_by_category' => 'array',
            'completion_rate' => 'float',
            'average_rating' => 'float',
            'last_proposal_at' => 'datetime',
            'refreshed_at' => 'datetime',
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
