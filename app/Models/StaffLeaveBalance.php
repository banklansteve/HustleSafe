<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeaveBalance extends Model
{
    protected $fillable = [
        'staff_user_id',
        'year',
        'annual_days',
        'sick_days',
        'emergency_days',
        'unpaid_days',
        'annual_days_used',
        'sick_days_used',
        'emergency_days_used',
        'unpaid_days_used',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public static function assignedColumnFor(string $leaveType): string
    {
        return match ($leaveType) {
            'sick' => 'sick_days',
            'emergency' => 'emergency_days',
            'unpaid' => 'unpaid_days',
            default => 'annual_days',
        };
    }

    public static function usedColumnFor(string $leaveType): string
    {
        return match ($leaveType) {
            'sick' => 'sick_days_used',
            'emergency' => 'emergency_days_used',
            'unpaid' => 'unpaid_days_used',
            default => 'annual_days_used',
        };
    }

    public function assignedDays(string $leaveType): int
    {
        return (int) $this->{self::assignedColumnFor($leaveType)};
    }

    public function usedDays(string $leaveType): int
    {
        return (int) $this->{self::usedColumnFor($leaveType)};
    }

    public function remainingDays(string $leaveType): int
    {
        return max(0, $this->assignedDays($leaveType) - $this->usedDays($leaveType));
    }
}

