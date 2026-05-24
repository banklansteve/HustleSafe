<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStaffActivitySummaryController extends Controller
{
    public function __invoke(Request $request, User $user): JsonResponse
    {
        abort_unless(in_array($user->role?->slug, ['admin', 'super_admin'], true), 404);

        $period = (string) $request->query('period', 'day');
        if (! in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'day';
        }

        $now = CarbonImmutable::now('Africa/Lagos');
        [$start, $end] = match ($period) {
            'week' => [$now->startOfWeek(), $now->endOfWeek()],
            'month' => [$now->startOfMonth(), $now->endOfMonth()],
            default => [$now->startOfDay(), $now->endOfDay()],
        };

        $logs = AdminActivityLog::query()
            ->where('actor_user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->limit(40)
            ->get();

        $actionCounts = $logs->groupBy('action')->map->count();

        return response()->json([
            'staff' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'period' => $period,
            'tiles' => [
                ['label' => 'Actions logged', 'value' => (string) $logs->count()],
                ['label' => 'Distinct action types', 'value' => (string) $actionCounts->count()],
                ['label' => 'Latest activity', 'value' => $logs->first()?->created_at?->diffForHumans() ?? '—'],
                ['label' => 'Period', 'value' => match ($period) {
                    'week' => 'This week',
                    'month' => 'This month',
                    default => 'Today',
                }],
            ],
            'timeline' => $logs->map(fn (AdminActivityLog $log) => [
                'id' => $log->id,
                'label' => str($log->action)->replace('.', ' ')->headline()->toString(),
                'when' => $log->created_at?->timezone('Africa/Lagos')->format('M j, g:i A') ?? '',
            ])->values()->all(),
        ]);
    }
}
