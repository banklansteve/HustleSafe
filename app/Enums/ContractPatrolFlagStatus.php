<?php

namespace App\Enums;

enum ContractPatrolFlagStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Acknowledged => 'Acknowledged',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }
}
