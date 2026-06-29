<?php

namespace App\Models;

use App\Enums\DisputeResolutionOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeResolutionRequest extends Model
{
    protected $fillable = [
        'quest_dispute_id',
        'requested_by_user_id',
        'party_role',
        'option',
        'terms',
        'status',
        'matched_request_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'terms' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(QuestDispute::class, 'quest_dispute_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function matchedRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'matched_request_id');
    }

    public function optionEnum(): ?DisputeResolutionOption
    {
        return DisputeResolutionOption::tryFrom((string) $this->option);
    }
}
