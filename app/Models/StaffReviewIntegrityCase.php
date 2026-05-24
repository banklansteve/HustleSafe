<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffReviewIntegrityCase extends Model
{
    protected $fillable = [
        'pattern_type',
        'pattern_key',
        'subject_user_id',
        'pattern_data',
        'status',
        'investigated_by_staff_id',
        'findings',
        'flagged_review_ids',
        'escalated_to_super_admin',
    ];

    protected function casts(): array
    {
        return [
            'pattern_data' => 'array',
            'flagged_review_ids' => 'array',
            'escalated_to_super_admin' => 'boolean',
        ];
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->subjectUser();
    }
}
