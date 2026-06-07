<?php

namespace App\Enums;

enum PremiumPatrolSubjectType: string
{
    case PremiumUser = 'premium_user';
    case BoostedQuest = 'boosted_quest';
    case Aggregate = 'aggregate';

    public function label(): string
    {
        return match ($this) {
            self::PremiumUser => 'Premium user',
            self::BoostedQuest => 'Boosted quest',
            self::Aggregate => 'Aggregate signal',
        };
    }
}
