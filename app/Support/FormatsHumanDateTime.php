<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class FormatsHumanDateTime
{
    public static function format(?CarbonInterface $value, ?string $timezone = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $tz = $timezone ?? config('app.timezone', 'Africa/Lagos');
        $date = $value->copy()->timezone($tz);

        $day = (int) $date->format('j');
        $month = $date->format('F');
        $year = $date->format('Y');
        $time = strtolower($date->format('g:ia'));

        return "{$day}".self::ordinalSuffix($day)." {$month}, {$year}. {$time}";
    }

    private static function ordinalSuffix(int $day): string
    {
        if ($day >= 11 && $day <= 13) {
            return 'th';
        }

        return match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
