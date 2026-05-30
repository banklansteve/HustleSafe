<?php

namespace App\Console\Commands;

use App\Services\Support\SupportTicketManagementService;
use Illuminate\Console\Command;

class RefreshSupportTicketAnalytics extends Command
{
    protected $signature = 'support-tickets:refresh-analytics';

    protected $description = 'Refresh cached support ticket analytics for the platform reports dashboard';

    public function handle(SupportTicketManagementService $tickets): int
    {
        $payload = $tickets->refreshAnalyticsCache();
        $this->info(sprintf(
            'Support ticket analytics refreshed: %d open, %.1f%% SLA breach rate.',
            $payload['open_tickets'] ?? 0,
            $payload['sla_breach_rate'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
