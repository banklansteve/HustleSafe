<?php

namespace App\Console\Commands;

use App\Models\StaffHrAlert;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateHrAlertsCommand extends Command
{
    protected $signature = 'hr:generate-alerts';

    protected $description = 'Generate automated HR alerts for staff governance';

    public function handle(): int
    {
        $now = CarbonImmutable::now();

        $this->generateSuspiciousActivityAlerts($now);
        $this->generatePerformanceAlerts($now);
        $this->generateRoleRevocationAlerts($now);
        $this->generateActivityBenchmarkAlerts($now);

        $this->info('HR alerts generated.');

        return self::SUCCESS;
    }

    private function generateSuspiciousActivityAlerts(CarbonImmutable $now): void
    {
        $threshold = max(1, (int) config('hr_management.suspicious_flags_30d_threshold', 2));
        $since = $now->subDays(30);

        $rows = DB::table('staff_hr_suspicious_activity_flags')
            ->select('staff_user_id', DB::raw('COUNT(*) as total'))
            ->where('flagged_at', '>=', $since)
            ->groupBy('staff_user_id')
            ->havingRaw('COUNT(*) > ?', [$threshold])
            ->get();

        foreach ($rows as $row) {
            $this->createAlertIfMissing(
                (int) $row->staff_user_id,
                'suspicious_activity_threshold',
                'high',
                "Suspicious activity flags exceeded {$threshold} within 30 days.",
                ['flag_count_30d' => (int) $row->total]
            );
        }
    }

    private function generatePerformanceAlerts(CarbonImmutable $now): void
    {
        $threshold = (float) config('hr_management.performance_threshold', 55);
        $monthA = $now->subMonthNoOverflow();
        $monthB = $now->subMonthsNoOverflow(2);

        $scores = DB::table('staff_performance_scores')
            ->select('staff_user_id', 'year', 'month', 'score', 'overridden', 'overridden_score')
            ->where(function ($query) use ($monthA, $monthB): void {
                $query
                    ->orWhere(function ($q) use ($monthA): void {
                        $q->where('year', $monthA->year)->where('month', $monthA->month);
                    })
                    ->orWhere(function ($q) use ($monthB): void {
                        $q->where('year', $monthB->year)->where('month', $monthB->month);
                    });
            })
            ->get()
            ->groupBy('staff_user_id');

        foreach ($scores as $staffUserId => $rows) {
            $map = [];
            foreach ($rows as $row) {
                $key = "{$row->year}-{$row->month}";
                $map[$key] = $row->overridden ? (float) ($row->overridden_score ?? $row->score) : (float) $row->score;
            }

            $a = $map["{$monthA->year}-{$monthA->month}"] ?? null;
            $b = $map["{$monthB->year}-{$monthB->month}"] ?? null;
            if ($a === null || $b === null || $a >= $threshold || $b >= $threshold) {
                continue;
            }

            $this->createAlertIfMissing(
                (int) $staffUserId,
                'performance_below_threshold_consecutive_months',
                'high',
                "Performance score remained below {$threshold} for two consecutive months.",
                ['month_a_score' => $a, 'month_b_score' => $b, 'threshold' => $threshold]
            );
        }
    }

    private function generateRoleRevocationAlerts(CarbonImmutable $now): void
    {
        $since = $now->subDays(7);

        $rows = DB::table('staff_role_assignments')
            ->select('staff_user_id', 'role_group', 'ends_on', 'revoked_at')
            ->where('status', 'revoked')
            ->whereNotNull('revoked_at')
            ->whereNotNull('ends_on')
            ->where('revoked_at', '>=', $since)
            ->whereRaw('DATE(revoked_at) < ends_on')
            ->get();

        foreach ($rows as $row) {
            $this->createAlertIfMissing(
                (int) $row->staff_user_id,
                'role_revoked_mid_assignment',
                'medium',
                'Role access was revoked before assignment end date.',
                ['role_group' => $row->role_group, 'assignment_end' => $row->ends_on]
            );
        }
    }

    private function generateActivityBenchmarkAlerts(CarbonImmutable $now): void
    {
        $benchmarks = DB::table('staff_activity_benchmarks')->get()->keyBy('role_group');
        if ($benchmarks->isEmpty()) {
            return;
        }

        $weekStartA = $now->startOfWeek()->subWeek();
        $weekEndA = $weekStartA->endOfWeek();
        $weekStartB = $weekStartA->copy()->subWeek();
        $weekEndB = $weekStartB->endOfWeek();

        $activeAssignments = DB::table('staff_role_assignments')
            ->select('staff_user_id', 'role_group')
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $now->toDateString())
            ->where(function ($q) use ($now): void {
                $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $now->toDateString());
            })
            ->get();

        foreach ($activeAssignments as $assignment) {
            $benchmark = $benchmarks->get($assignment->role_group);
            if (! $benchmark) {
                continue;
            }

            $min = (int) $benchmark->minimum_weekly_actions;
            if ($min <= 0) {
                continue;
            }

            $countA = (int) DB::table('staff_action_logs')
                ->where('staff_user_id', $assignment->staff_user_id)
                ->whereBetween('acted_at', [$weekStartA, $weekEndA])
                ->count();
            $countB = (int) DB::table('staff_action_logs')
                ->where('staff_user_id', $assignment->staff_user_id)
                ->whereBetween('acted_at', [$weekStartB, $weekEndB])
                ->count();

            if ($countA >= $min || $countB >= $min) {
                continue;
            }

            $this->createAlertIfMissing(
                (int) $assignment->staff_user_id,
                'activity_benchmark_breach_consecutive_weeks',
                'medium',
                "Weekly activity benchmark breach for two consecutive weeks ({$countB}, {$countA} vs {$min}).",
                ['week_a_count' => $countA, 'week_b_count' => $countB, 'minimum' => $min, 'role_group' => $assignment->role_group]
            );
        }
    }

    private function createAlertIfMissing(int $staffUserId, string $alertType, string $severity, string $message, array $payload): void
    {
        $recentExists = StaffHrAlert::query()
            ->where('staff_user_id', $staffUserId)
            ->where('alert_type', $alertType)
            ->where('triggered_at', '>=', now()->subDay())
            ->exists();

        if ($recentExists) {
            return;
        }

        StaffHrAlert::query()->create([
            'staff_user_id' => $staffUserId,
            'alert_type' => $alertType,
            'severity' => $severity,
            'message' => $message,
            'payload' => $payload,
            'triggered_at' => now(),
        ]);
    }
}
