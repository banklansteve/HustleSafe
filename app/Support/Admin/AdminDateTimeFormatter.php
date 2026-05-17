<?php

namespace App\Support\Admin;

use Carbon\CarbonInterface;
use DateTimeInterface;

final class AdminDateTimeFormatter
{
    public static function isDateColumn(string $column): bool
    {
        return str_ends_with($column, '_at')
            || str_ends_with($column, '_on')
            || in_array($column, ['date_of_birth', 'submitted_at', 'reviewed_at', 'expires_at', 'due_at'], true);
    }

    public static function formatValue(mixed $value, string $column): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! $value instanceof DateTimeInterface) {
            return $value;
        }

        $tz = config('app.timezone', 'UTC');
        $dt = $value instanceof CarbonInterface
            ? $value->copy()->timezone($tz)
            : \Carbon\Carbon::instance($value)->timezone($tz);

        if (in_array($column, ['date_of_birth', 'scheduled_start_date', 'estimated_delivery_date', 'proposed_completion_date', 'planned_start_date', 'planned_finish_date', 'expires_on'], true)) {
            return $dt->format('d/m/Y');
        }

        return $dt->format('d-m-Y h:ia');
    }
}
