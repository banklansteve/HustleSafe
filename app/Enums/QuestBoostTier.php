<?php

namespace App\Enums;

enum QuestBoostTier: string
{
    case ThreeDay = '3_day';
    case SevenDay = '7_day';
    case FourteenDay = '14_day';
    case ThirtyDay = '30_day';

    public function label(): string
    {
        return match ($this) {
            self::ThreeDay => '3 Days',
            self::SevenDay => '7 Days',
            self::FourteenDay => '14 Days',
            self::ThirtyDay => '30 Days',
        };
    }

    public function durationHours(): int
    {
        return match ($this) {
            self::ThreeDay => 72,
            self::SevenDay => 168,
            self::FourteenDay => 336,
            self::ThirtyDay => 720,
        };
    }

    public function settingsKey(): string
    {
        return match ($this) {
            self::ThreeDay => 'quest_boosts.price_3_day_minor',
            self::SevenDay => 'quest_boosts.price_7_day_minor',
            self::FourteenDay => 'quest_boosts.price_14_day_minor',
            self::ThirtyDay => 'quest_boosts.price_30_day_minor',
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::ThreeDay,
            self::SevenDay,
            self::FourteenDay,
            self::ThirtyDay,
        ];
    }
}
