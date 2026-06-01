<?php

namespace App\Enums;

enum FreelancerSubscriptionStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case PendingRenewal = 'pending_renewal';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::PendingRenewal => 'Pending Renewal',
        };
    }
}
