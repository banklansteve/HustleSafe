<?php

namespace App\Enums;

enum ReconciliationExceptionStatus: string
{
    case Open = 'open';
    case UnderInvestigation = 'under_investigation';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderInvestigation => 'Under Investigation',
            self::Resolved => 'Resolved',
        };
    }
}
