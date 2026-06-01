<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAdminReportExportJob;
use App\Jobs\SendAdminScheduledReportJob;
use App\Models\AdminReportExport;
use App\Models\AdminSavedReport;
use App\Models\LocalGovernment;
use App\Models\QuestCategory;
use App\Models\State;
use App\Models\User;
use App\Services\Admin\AdvancedReportEngine;
use App\Services\Admin\AdminAnalyticsService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportsController extends Controller
{
    public function __construct(
        private AdminAnalyticsService $analytics,
        private AdvancedReportEngine $reports,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Reports/Index', [
            'kpi' => $this->analytics->kpiSnapshot(),
            'charts' => $this->analytics->financialReportsCharts(),
            'leaderboards' => ['freelancers' => [], 'clients' => []],
            'generated_at' => now()->toIso8601String(),
            'catalog' => $this->reports->catalog(),
            'quick_stats' => $this->reports->landingStats(),
            'saved_reports' => $this->reports->savedReportsForUi(),
            'filter_options' => [
                'states' => State::query()->orderBy('name')->get(['id', 'name']),
                'local_governments' => LocalGovernment::query()->orderBy('name')->get(['id', 'state_id', 'name']),
                'categories' => QuestCategory::query()->whereNotNull('parent_id')->orderBy('name')->get(['id', 'name']),
                'users' => User::query()
                    ->with('role:id,slug,name')
                    ->orderBy('name')
                    ->limit(250)
                    ->get(['id', 'name', 'email', 'role_id'])
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role?->slug ?? $user->role?->name,
                    ]),
            ],
            'recent_exports' => AdminReportExport::query()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AdminReportExport $export) => [
                    'id' => $export->id,
                    'report_name' => $export->report_name,
                    'format' => $export->format,
                    'status' => $export->status,
                    'download_url' => $export->downloadUrl(),
                    'expires_at' => $export->expires_at?->toIso8601String(),
                ]),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        return response()->json($this->reports->run($request->all()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'report_type' => ['required', 'string', 'max:80'],
            'builder_config' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
            'date_preset' => ['required', 'string', 'max:40'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'schedule_frequency' => ['nullable', 'in:daily,weekly,monthly'],
            'schedule_recipients' => ['nullable', 'array'],
            'schedule_recipients.*' => ['email'],
        ]);

        $report = AdminSavedReport::query()->create([
            ...$data,
            'user_id' => $request->user()?->id,
            'next_run_at' => $this->nextRunAt($data['schedule_frequency'] ?? null),
        ]);

        return back()->with('success', "Saved report {$report->name}.");
    }

    public function runSaved(AdminSavedReport $report): JsonResponse
    {
        $report->forceFill(['last_run_at' => now()])->save();

        return response()->json($this->reports->run([
            'report_type' => $report->report_type,
            'builder_config' => $report->builder_config,
            ...($report->builder_config ?? []),
            'filters' => $report->filters ?? [],
            'date_preset' => $report->date_preset,
            'date_from' => $report->date_from?->toDateString(),
            'date_to' => $report->date_to?->toDateString(),
        ]));
    }

    public function exportReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:pdf,xlsx,csv'],
            'report_name' => ['required', 'string', 'max:160'],
            'report_type' => ['required', 'string', 'max:80'],
            'payload' => ['required', 'array'],
            'saved_report_id' => ['nullable', 'integer', 'exists:admin_saved_reports,id'],
        ]);

        $export = AdminReportExport::query()->create([
            'admin_saved_report_id' => $data['saved_report_id'] ?? null,
            'user_id' => $request->user()?->id,
            'report_name' => $data['report_name'],
            'report_type' => $data['report_type'],
            'format' => $data['format'],
            'status' => 'pending',
            'payload' => $data['payload'],
            'expires_at' => now()->addDays(7),
        ]);

        GenerateAdminReportExportJob::dispatch($export);

        return response()->json([
            'message' => 'Preparing export. The download link will appear when ready.',
            'export_id' => $export->id,
        ]);
    }

    public function runNowAndEmail(AdminSavedReport $report): RedirectResponse
    {
        SendAdminScheduledReportJob::dispatch($report);

        return back()->with('success', "Queued {$report->name} for email delivery.");
    }

    public function export(): StreamedResponse
    {
        $payload = $this->analytics->dashboardPayload();
        $rows = [];
        foreach ($payload['kpi'] as $key => $value) {
            $rows[] = [$key, (string) $value];
        }

        return AdminCsv::download('admin-reports-'.now()->format('Y-m-d-His').'.csv', ['metric', 'value'], function ($out) use ($rows, $payload): void {
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fputcsv($out, []);
            fputcsv($out, ['generated_at', $payload['generated_at']]);
        });
    }

    private function nextRunAt(?string $frequency): ?\Carbon\CarbonInterface
    {
        return match ($frequency) {
            'daily' => now()->addDay()->startOfDay()->addHours(8),
            'weekly' => now()->addWeek()->startOfWeek()->addHours(8),
            'monthly' => now()->addMonthNoOverflow()->startOfMonth()->addHours(8),
            default => null,
        };
    }
}
