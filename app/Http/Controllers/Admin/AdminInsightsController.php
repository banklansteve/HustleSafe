<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAnalyticsService;
use App\Services\Admin\AdminContentHealthInsightsService;
use App\Services\Admin\AdminInsightsService;
use App\Services\Support\SupportTicketManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminInsightsController extends Controller
{
    public function __invoke(
        AdminInsightsService $insights,
        AdminAnalyticsService $analytics,
        AdminContentHealthInsightsService $contentHealth,
        SupportTicketManagementService $supportTickets,
    ): Response {
        return Inertia::render('Admin/Insights/Index', [
            'insights' => $insights->payload(),
            'content_health' => $contentHealth->summary(),
            'content_health_drill_down_url' => route('admin.insights.content-health'),
            'operational_charts' => $analytics->operationalInsightsCharts(),
            'operational_leaderboards' => [
                'freelancers' => $analytics->topFreelancers(),
                'clients' => $analytics->topClients(),
            ],
            'support_ticket_analytics' => $supportTickets->analytics(),
        ]);
    }

    public function contentHealth(Request $request, AdminContentHealthInsightsService $contentHealth): JsonResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'in:quests,proposals,contracts'],
            'band' => ['required', 'in:healthy,warning,critical'],
            'limit' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return response()->json($contentHealth->drillDown(
            $validated['module'],
            $validated['band'],
            (int) ($validated['limit'] ?? 15),
        ));
    }
}
