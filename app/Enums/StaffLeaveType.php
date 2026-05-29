<?php

namespace App\Enums;

enum StaffLeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Emergency = 'emergency';
    case Unpaid = 'unpaid';

    public function countsAgainstAnnualQuota(): bool
    {
        return $this === self::Annual;
    }
}
