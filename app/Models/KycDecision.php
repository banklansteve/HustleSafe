<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDecision extends Model
{
    protected $fillable = [
        'kyc_review_case_id',
        'admin_user_id',
        'action',
        'reason_code',
        'note',
        'correction_fields',
        'portfolio_scores',
        'time_to_decision_seconds',
    ];

    protected function casts(): array
    {
        return [
            'correction_fields' => 'array',
            'portfolio_scores' => 'array',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(KycReviewCase::class, 'kyc_review_case_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
