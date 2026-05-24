<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAnalyticsService;
use App\Services\Admin\AdminActivityFeedService;
use App\Support\Admin\AdminManagementRegistry;
use App\Support\AdminCsv;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        private AdminAnalyticsService $analytics,
        private AdminActivityFeedService $feed,
    ) {}

    public function __invoke(): Response
    {
        $payload = $this->analytics->dashboardPayload();
        $payload['resource_groups'] = AdminManagementRegistry::groupedForUi();
        $this->feed->seedRecentFromExistingData();
        $payload['live_activity'] = $this->feed->widgetPayload(3);

        return Inertia::render('Admin/Dashboard', $payload);
    }

    public function export(): StreamedResponse
    {
        $tz = config('app.timezone');
        $kpi = $this->analytics->kpiSnapshot();
        $kpi['generated_at'] = Carbon::now($tz)->toIso8601String();

        return AdminCsv::download('dashboard-metrics-'.now()->format('Y-m-d-His').'.csv', array_keys($kpi), function ($out) use ($kpi): void {
            fputcsv($out, array_values($kpi));
        });
    }
}
