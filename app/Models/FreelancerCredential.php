<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerCredential extends Model
{
    protected $fillable = [
        'user_id',
        'credential_type',
        'title',
        'issuing_authority',
        'reference_number',
        'issued_on',
        'expires_on',
        'coverage_summary',
        'document_path',
        'is_verified',
        'is_public',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
            'is_verified' => 'boolean',
            'is_public' => 'boolean',
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
