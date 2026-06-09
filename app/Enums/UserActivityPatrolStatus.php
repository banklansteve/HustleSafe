<?php

namespace App\Enums;

enum UserActivityPatrolStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Watchlisted = 'watchlisted';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderReview => 'Under Review',
            self::Watchlisted => 'Watchlisted',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }
}
