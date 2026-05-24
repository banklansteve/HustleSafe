<?php

namespace App\Console\Commands;

use App\Services\Support\CustomerSupportService;
use Illuminate\Console\Command;

class CloseInactiveCustomerSupportChatsCommand extends Command
{
    protected $signature = 'customer-support:close-inactive';

    protected $description = 'Close customer support chats after mutual inactivity';

    public function handle(CustomerSupportService $service): int
    {
        if (! $service->tablesReady()) {
            $this->warn('Support tables not ready.');

            return self::SUCCESS;
        }

        $closed = $service->closeInactiveChats();
        $this->info("Closed {$closed} inactive conversation(s).");

        return self::SUCCESS;
    }
}
