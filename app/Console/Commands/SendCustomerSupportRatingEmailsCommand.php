<?php

namespace App\Console\Commands;

use App\Services\Support\CustomerSupportService;
use Illuminate\Console\Command;

class SendCustomerSupportRatingEmailsCommand extends Command
{
    protected $signature = 'customer-support:send-rating-emails';

    protected $description = 'Send post-closure rating emails for customer support chats';

    public function handle(CustomerSupportService $service): int
    {
        if (! $service->tablesReady()) {
            $this->warn('Support tables not ready.');

            return self::SUCCESS;
        }

        $sent = $service->dispatchDueRatingEmails();
        $this->info("Sent {$sent} rating email(s).");

        return self::SUCCESS;
    }
}
