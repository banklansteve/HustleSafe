<?php

namespace App\Http\Controllers\Operations;

use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\User;
use App\Services\Operations\StaffOperationsDashboardService;
use App\Support\AdminCsv;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsDashboardController extends Controller
{
    public function __invoke(StaffOperationsDashboardService $dashboard): Response
    {
        $tz = config('app.timezone');
        $kpi = $this->kpiSnapshot();

        return Inertia::render('Operations/Dashboard', [
            'kpi' => $kpi,
            'payload' => $dashboard->payload(request()->user()),
            'generated_at' => Carbon::now($tz)->toIso8601String(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $tz = config('app.timezone');
        $kpi = $this->kpiSnapshot();
        $kpi['generated_at'] = Carbon::now($tz)->toIso8601String();

        return AdminCsv::download('operations-dashboard-'.now()->format('Y-m-d-His').'.csv', array_keys($kpi), function ($out) use ($kpi): void {
            fputcsv($out, array_values($kpi));
        });
    }

    /**
     * @return array<string, int|string>
     */
    private function kpiSnapshot(): array
    {
        return [
            'users_total' => User::query()->count(),
            'users_new_30d' => User::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'quests_open' => Quest::query()->where('status', QuestStatus::Open)->count(),
            'quests_in_progress' => Quest::query()->where('status', QuestStatus::InProgress)->count(),
            'quests_completed' => Quest::query()->where('status', QuestStatus::Completed)->count(),
            'disputes_open' => QuestDispute::query()->whereIn('status', [
                QuestDisputeStatus::Open,
                QuestDisputeStatus::SelfResolving,
                QuestDisputeStatus::Escalated,
                QuestDisputeStatus::AwaitingRuling,
            ])->count(),
            'escrow_funded_active' => Quest::query()
                ->where('status', QuestStatus::InProgress)
                ->where('escrow_status', 'funded')
                ->count(),
        ];
    }
}
