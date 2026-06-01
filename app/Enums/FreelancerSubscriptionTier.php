<?php

namespace App\Enums;

enum FreelancerSubscriptionTier: string
{
    case Free = 'free';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
        };
    }
}
