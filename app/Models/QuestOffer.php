<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestOffer extends Model
{
    protected $fillable = [
        'quest_id',
        'freelancer_id',
        'status',
        'pitch',
        'scope_detail',
        'warranty_terms',
        'proposed_completion_date',
        'planned_start_date',
        'planned_finish_date',
        'estimated_duration_days',
        'corrections_included',
        'corrections_rounds',
        'progress_report_frequency',
        'materials',
        'pricing_snapshot',
        'quoted_amount_minor',
        'accepted_at',
        'declined_at',
        'withdrawn_at',
        'shortlisted_at',
        'client_pinned_at',
        'client_view_count',
        'last_client_view_at',
        'freelancer_edit_deadline_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'materials' => 'array',
            'pricing_snapshot' => 'array',
            'proposed_completion_date' => 'date',
            'planned_start_date' => 'date',
            'planned_finish_date' => 'date',
            'corrections_included' => 'boolean',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'shortlisted_at' => 'datetime',
            'client_pinned_at' => 'datetime',
            'last_client_view_at' => 'datetime',
            'freelancer_edit_deadline_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quest, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }
}
