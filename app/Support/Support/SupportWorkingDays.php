<?php

namespace App\Support\Support;

use Carbon\CarbonInterface;

final class SupportWorkingDays
{
    public static function addWorkingDays(CarbonInterface $start, int $days): CarbonInterface
    {
        $current = $start->copy()->startOfDay();
        $added = 0;

        while ($added < $days) {
            $current = $current->addDay();
            if (! $current->isWeekend()) {
                $added++;
            }
        }

        return $current->endOfDay();
    }
}
