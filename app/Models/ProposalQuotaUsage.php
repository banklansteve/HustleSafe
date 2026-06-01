<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalQuotaUsage extends Model
{
    protected $fillable = [
        'freelancer_id',
        'month',
        'proposals_count',
        'plan_tier',
    ];

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }
}
