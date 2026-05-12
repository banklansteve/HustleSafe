<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerBusinessProfile extends Model
{
    protected $fillable = [
        'user_id',
        'cac_registration_number',
        'cac_verification_status',
        'cac_verified_at',
        'cac_last_checked_at',
        'cac_verification_notes',
    ];

    protected function casts(): array
    {
        return [
            'cac_verified_at' => 'datetime',
            'cac_last_checked_at' => 'datetime',
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
