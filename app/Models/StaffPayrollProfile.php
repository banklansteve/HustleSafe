<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPayrollProfile extends Model
{
    protected $fillable = [
        'staff_user_id',
        'base_salary',
        'currency',
        'payment_frequency',
        'effective_from',
        'bank_details_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
