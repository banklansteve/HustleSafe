<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailBroadcastJob;
use App\Models\EmailBroadcast;
use Illuminate\Console\Command;

class ProcessScheduledEmailBroadcastsCommand extends Command
{
    protected $signature = 'email-broadcasts:process-scheduled';

    protected $description = 'Queue scheduled email broadcasts whose send time has arrived.';

    public function handle(): int
    {
        $broadcasts = EmailBroadcast::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->limit(50)
            ->get();

        foreach ($broadcasts as $broadcast) {
            $broadcast->forceFill(['status' => 'queued'])->save();
            SendEmailBroadcastJob::dispatch($broadcast->id);
        }

        $this->info('Queued '.$broadcasts->count().' scheduled broadcast(s).');

        return self::SUCCESS;
    }
}
