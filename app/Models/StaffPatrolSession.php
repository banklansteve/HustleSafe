<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffPatrolSession extends Model
{
    protected $fillable = [
        'staff_user_id',
        'content_type',
        'category_id',
        'date_from',
        'date_to',
        'sample_size',
        'reviewed_count',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StaffPatrolItem::class);
    }
}
