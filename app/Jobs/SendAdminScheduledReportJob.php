<?php

namespace App\Jobs;

use App\Mail\AdminScheduledReportMail;
use App\Models\AdminReportExport;
use App\Models\AdminSavedReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAdminScheduledReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AdminSavedReport $report
    ) {}

    public function handle(): void
    {
        $payload = [
            'report_type' => $this->report->report_type,
            ...($this->report->builder_config ?? []),
            'filters' => $this->report->filters ?? [],
            'date_preset' => $this->report->date_preset,
            'date_from' => $this->report->date_from?->toDateString(),
            'date_to' => $this->report->date_to?->toDateString(),
        ];

        $export = AdminReportExport::query()->create([
            'admin_saved_report_id' => $this->report->id,
            'user_id' => $this->report->user_id,
            'report_name' => $this->report->name,
            'report_type' => $this->report->report_type,
            'format' => 'pdf',
            'status' => 'pending',
            'payload' => $payload,
            'expires_at' => now()->addDays(7),
        ]);

        GenerateAdminReportExportJob::dispatchSync($export);
        $export->refresh();

        foreach ($this->report->schedule_recipients ?? [] as $email) {
            Mail::to($email)->queue(new AdminScheduledReportMail($this->report, $export));
        }

        $this->report->forceFill([
            'last_run_at' => now(),
            'next_run_at' => match ($this->report->schedule_frequency) {
                'daily' => now()->addDay(),
                'weekly' => now()->addWeek(),
                'monthly' => now()->addMonthNoOverflow(),
                default => null,
            },
        ])->save();
    }
}
