<?php

namespace App\Enums;

enum PremiumPatrolFlagStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Resolved = 'resolved';
    case AutoResolved = 'auto_resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Dismissed => 'Dismissed',
            self::Resolved => 'Resolved',
            self::AutoResolved => 'Auto-resolved',
        };
    }
}
