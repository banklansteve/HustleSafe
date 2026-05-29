<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffHrComplianceCase extends Model
{
    protected $table = 'staff_hr_compliance_cases';

    protected $fillable = [
        'staff_user_id',
        'severity',
        'status',
        'incident_note',
        'evidence',
        'opened_by_user_id',
        'updated_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
