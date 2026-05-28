<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewAuthenticitySignal extends Model
{
    protected $fillable = [
        'review_id',
        'signal_type',
        'label',
        'metadata',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'confidence' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
