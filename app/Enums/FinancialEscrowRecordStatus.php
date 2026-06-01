<?php

namespace App\Enums;

enum FinancialEscrowRecordStatus: string
{
    case Held = 'held';
    case Released = 'released';
    case Refunded = 'refunded';
    case PartiallyReleased = 'partially_released';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Held',
            self::Released => 'Released',
            self::Refunded => 'Refunded',
            self::PartiallyReleased => 'Partially Released',
            self::Disputed => 'Disputed',
        };
    }
}
