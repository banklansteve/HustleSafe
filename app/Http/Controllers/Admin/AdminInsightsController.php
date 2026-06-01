<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAnalyticsService;
use App\Services\Admin\AdminInsightsService;
use App\Services\Support\SupportTicketManagementService;
use Inertia\Inertia;
use Inertia\Response;

class AdminInsightsController extends Controller
{
    public function __invoke(
        AdminInsightsService $insights,
        AdminAnalyticsService $analytics,
        SupportTicketManagementService $supportTickets,
    ): Response {
        return Inertia::render('Admin/Insights/Index', [
            'insights' => $insights->payload(),
            'operational_charts' => $analytics->operationalInsightsCharts(),
            'operational_leaderboards' => [
                'freelancers' => $analytics->topFreelancers(),
                'clients' => $analytics->topClients(),
            ],
            'support_ticket_analytics' => $supportTickets->analytics(),
        ]);
    }
}
