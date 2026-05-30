<?php

namespace App\Console\Commands;

use App\Services\Support\SupportTicketManagementService;
use Illuminate\Console\Command;

class FlagOverdueSupportTickets extends Command
{
    protected $signature = 'support-tickets:flag-overdue';

    protected $description = 'Flag in-progress support tickets that have breached their expected resolution date';

    public function handle(SupportTicketManagementService $tickets): int
    {
        $count = $tickets->flagOverdueTickets();
        $this->info("Flagged {$count} overdue ticket(s).");

        return self::SUCCESS;
    }
}
