<?php

namespace App\Enums;

enum QuestBoostStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case ManuallyCancelled = 'manually_cancelled';
    case ManuallyEndedEarly = 'manually_ended_early';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::ManuallyCancelled => 'Manually Cancelled',
            self::ManuallyEndedEarly => 'Manually Ended Early',
        };
    }
}
