<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycAuditEvent extends Model
{
    protected $fillable = [
        'kyc_review_case_id',
        'admin_user_id',
        'event',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
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
