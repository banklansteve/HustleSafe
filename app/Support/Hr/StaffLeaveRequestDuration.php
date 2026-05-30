<?php

namespace App\Support\Hr;

use App\Enums\StaffLeaveRequestStatus;
use App\Enums\StaffLeaveType;
use App\Models\StaffLeaveBalance;
use App\Models\StaffLeaveRequest;
use Carbon\CarbonImmutable;

class StaffLeaveRequestDuration
{
    public static function calculateDays(string $durationType, string $startDate, ?string $endDate): int
    {
        $start = CarbonImmutable::parse($startDate);
        $resolvedEnd = match ($durationType) {
            'multiple_days' => CarbonImmutable::parse((string) $endDate),
            default => $start,
        };

        return match ($durationType) {
            'multiple_days' => max(2, $start->diffInDays($resolvedEnd) + 1),
            default => 1,
        };
    }

    public static function calendarYear(string $startDate): int
    {
        return (int) CarbonImmutable::parse($startDate)->format('Y');
    }

    /**
     * @return array{remaining: int, pending: int, effective: int, balance: StaffLeaveBalance}
     */
    public static function balanceSnapshot(int $staffUserId, string $leaveType, int $year): array
    {
        $balance = StaffLeaveBalance::query()->firstOrCreate(
            ['staff_user_id' => $staffUserId, 'year' => $year],
            ['annual_days' => 0, 'sick_days' => 0, 'emergency_days' => 0, 'unpaid_days' => 0]
        );

        $remaining = $balance->remainingDays($leaveType);
        $pending = (int) StaffLeaveRequest::query()
            ->where('staff_user_id', $staffUserId)
            ->where('leave_type', $leaveType)
            ->where('status', StaffLeaveRequestStatus::Pending->value)
            ->whereYear('start_date', $year)
            ->sum('days_requested');

        return [
            'remaining' => $remaining,
            'pending' => $pending,
            'effective' => max(0, $remaining - $pending),
            'balance' => $balance,
        ];
    }

    public static function insufficientBalanceMessage(
        string $selectedType,
        int $year,
        int $requestedDays,
        int $effectiveRemaining,
        StaffLeaveBalance $balance,
    ): string {
        $selectedLabel = ucfirst($selectedType);
        $alternates = [];

        foreach (StaffLeaveType::cases() as $type) {
            if ($type->value === $selectedType) {
                continue;
            }

            $snapshot = self::balanceSnapshot((int) $balance->staff_user_id, $type->value, $year);
            if ($snapshot['effective'] > 0) {
                $alternates[] = sprintf(
                    '%s (%d day(s) available)',
                    ucfirst($type->value),
                    $snapshot['effective']
                );
            }
        }

        $base = sprintf(
            'You only have %d day(s) of %s leave available for %d (including pending requests), but this request needs %d day(s).',
            $effectiveRemaining,
            strtolower($selectedLabel),
            $year,
            $requestedDays,
        );

        if ($alternates === []) {
            return $base.' Please contact HR to adjust your leave balance or choose different dates.';
        }

        return $base.' Try requesting '.implode(', ', $alternates).' instead, or contact HR.';
    }
}
