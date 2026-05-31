<?php

namespace App\Enums;

enum ContractStatus: string
{
    case PendingEscrow = 'pending_escrow';
    case Active = 'active';
    case AmendmentPending = 'amendment_pending';
    case Completed = 'completed';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingEscrow => 'Pending Escrow',
            self::Active => 'Active',
            self::AmendmentPending => 'Amendment Pending',
            self::Completed => 'Completed',
            self::Disputed => 'Disputed',
            self::Cancelled => 'Cancelled',
        };
    }
}
