<?php

namespace App\Console\Commands;

use App\Jobs\SendAdminScheduledReportJob;
use App\Models\AdminReportExport;
use App\Models\AdminSavedReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessScheduledAdminReportsCommand extends Command
{
    protected $signature = 'admin-reports:process-scheduled';

    protected $description = 'Queue due admin scheduled reports and purge expired report exports.';

    public function handle(): int
    {
        AdminSavedReport::query()
            ->whereNotNull('schedule_frequency')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->each(fn (AdminSavedReport $report) => SendAdminScheduledReportJob::dispatch($report));

        AdminReportExport::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->each(function (AdminReportExport $export): void {
                if ($export->path) {
                    Storage::disk($export->disk)->delete($export->path);
                }
                $export->delete();
            });

        $this->info('Processed scheduled admin reports and expired exports.');

        return self::SUCCESS;
    }
}
