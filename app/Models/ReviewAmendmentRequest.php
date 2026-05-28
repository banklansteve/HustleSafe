<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewAmendmentRequest extends Model
{
    protected $fillable = [
        'review_id',
        'issued_by',
        'instructions',
        'required_changes',
        'expires_at',
        'status',
        'responded_at',
        'default_action',
    ];

    protected function casts(): array
    {
        return [
            'required_changes' => 'array',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->expires_at->isFuture();
    }
}
