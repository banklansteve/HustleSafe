<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPayslip extends Model
{
    protected $fillable = [
        'staff_user_id',
        'year',
        'month',
        'gross_pay',
        'bonuses',
        'deductions',
        'net_pay',
        'pdf_path',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
