<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Finance\FinancialAuditReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyFinancialReportsCommand extends Command
{
    protected $signature = 'financial:generate-monthly-reports';

    protected $description = 'Generate VAT and platform fee audit reports for the previous calendar month';

    public function handle(FinancialAuditReportService $reports): int
    {
        $admin = User::query()->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        if ($admin === null) {
            $this->warn('No super admin found — skipping scheduled report generation.');

            return self::SUCCESS;
        }

        $start = Carbon::now()->subMonth()->startOfMonth()->startOfDay();
        $end = Carbon::now()->subMonth()->endOfMonth()->endOfDay();

        $reports->generate($admin, 'vat_audit', $start, $end);
        $reports->generate($admin, 'platform_fee_audit', $start, $end);

        $this->info('Generated VAT and platform fee audit reports for '.$start->format('F Y').'.');

        return self::SUCCESS;
    }
}
