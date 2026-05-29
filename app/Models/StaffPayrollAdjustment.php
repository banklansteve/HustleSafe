<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPayrollAdjustment extends Model
{
    protected $fillable = [
        'staff_user_id',
        'type',
        'deduction_mode',
        'deduction_basis',
        'deduction_percentage',
        'deduction_custom_base_amount',
        'amount',
        'reason',
        'effective_date',
        'is_recurring',
        'reference',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'is_recurring' => 'boolean',
            'deduction_percentage' => 'float',
            'deduction_custom_base_amount' => 'float',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
