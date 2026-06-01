<?php

namespace App\Enums;

enum SubscriptionBillingCycle: string
{
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Month => 'Monthly',
            self::Year => 'Annual',
        };
    }
}
