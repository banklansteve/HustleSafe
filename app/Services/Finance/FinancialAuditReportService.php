<?php

namespace App\Services\Finance;

use App\Models\FinancialAuditReport;
use App\Models\FinancialEscrowRecord;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\VatRemittance;
use App\Support\NgnMoney;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FinancialAuditReportService
{
    /**
     * @return array<string, mixed>
     */
    public function vatReport(Request $request): array
    {
        return $this->contractReport($request, 'vat');
    }

    /**
     * @return array<string, mixed>
     */
    public function platformFeeReport(Request $request): array
    {
        $source = (string) $request->query('source', 'escrow_fees');
        if (! in_array($source, ['escrow_fees', 'quest_boosts', 'premium_freelancers'], true)) {
            $source = 'escrow_fees';
        }

        [$from, $to] = $this->resolveRange($request);
        $payload = $this->contractReport($request, 'platform_fee');
        $payload['revenue_sources'] = $this->revenueSources();
        $payload['revenue_summary'] = $this->revenueSummary($from, $to);
        $payload['filters']['source'] = $source;

        if ($source !== 'escrow_fees') {
            $payload['data'] = [];
            $payload['meta'] = ['current_page' => 1, 'last_page' => 1, 'total' => 0];
            $payload['totals'] = [
                'count' => 0,
                'gross_display' => NgnMoney::format(0),
                'revenue_display' => NgnMoney::format(0),
            ];
            $payload['coming_soon'] = true;
            $payload['coming_soon_message'] = match ($source) {
                'quest_boosts' => __('Quest boost revenue will appear here once paid promotions launch.'),
                'premium_freelancers' => __('Premium freelancer subscription revenue will appear here once subscriptions launch.'),
                default => __('Coming soon.'),
            };
        }

        return $payload;
    }

    public function exportVatReportCsv(Request $request): StreamedResponse
    {
        return $this->exportContractReportCsv($request, 'vat');
    }

    public function exportPlatformFeeReportCsv(Request $request): StreamedResponse
    {
        $source = (string) $request->query('source', 'escrow_fees');
        if ($source !== 'escrow_fees') {
            $filename = $source.'-report-'.now()->format('Ymd-His').'.csv';

            return response()->streamDownload(function () use ($source): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Source', $source]);
                fputcsv($out, ['Status', 'Coming soon — no transactions yet']);
                fclose($out);
            }, $filename);
        }

        return $this->exportContractReportCsv($request, 'platform_fee');
    }

    public function recordVatRemittance(User $admin, array $data): VatRemittance
    {
        return VatRemittance::query()->create([
            'quarter_label' => $data['quarter_label'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'amount_minor' => (int) $data['amount_minor'],
            'remittance_reference' => $data['remittance_reference'],
            'remitted_at' => $data['remitted_at'] ?? now(),
            'recorded_by_user_id' => $admin->id,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function generate(User $admin, string $type, Carbon $start, Carbon $end): FinancialAuditReport
    {
        $request = Request::create('/', 'GET', [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'date_field' => $type === 'vat_audit' ? 'fee_recognised_at' : 'released_at',
        ]);

        $reportType = $type === 'vat_audit' ? 'vat' : 'platform_fee';
        $rows = $this->contractQuery($request, $start, $end)
            ->limit(5000)
            ->get()
            ->map(fn (FinancialEscrowRecord $r) => $this->contractReportRow($r, $reportType))
            ->all();

        if ($reportType === 'vat') {
            $cumulative = 0;
            $rows = array_map(function (array $row) use (&$cumulative) {
                $cumulative += (int) $row['vat_minor'];
                $row['cumulative_vat_display'] = NgnMoney::format($cumulative);

                return $row;
            }, $rows);
        }

        $summary = [
            'transaction_count' => count($rows),
            'total_vat_minor' => array_sum(array_column($rows, 'vat_minor')),
            'total_fee_minor' => array_sum(array_column($rows, 'platform_revenue_minor')),
        ];

        $slug = Str::slug($type.'-'.$start->format('Ymd').'-'.$end->format('Ymd').'-'.Str::lower(Str::random(6)));
        $csvPath = "financial-audit/{$slug}.csv";
        Storage::disk('local')->put($csvPath, $this->contractRowsToCsv($reportType, $rows));

        return FinancialAuditReport::query()->create([
            'type' => $type,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'generated_by_user_id' => $admin->id,
            'generated_at' => now(),
            'csv_path' => $csvPath,
            'pdf_path' => null,
            'summary' => $summary,
        ]);
    }

    public function exportReconciliationCsv(Request $request): StreamedResponse
    {
        $service = app(FinancialReconciliationReportService::class);
        $payload = $service->report($request);
        $from = $payload['filters']['from'];
        $to = $payload['filters']['to'];
        $asOf = $payload['filters']['as_of'];
        $filename = "reconciliation-{$from}-to-{$to}-asof-{$asOf}.csv";

        return response()->streamDownload(function () use ($payload): void {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reconciliation summary']);
            fputcsv($out, ['Period', $payload['filters']['label']]);
            fputcsv($out, ['Escrow funded', NgnMoney::csvMajor((int) $payload['period']['escrow_funded_minor'])]);
            fputcsv($out, ['Platform fees recognised', NgnMoney::csvMajor((int) $payload['period']['platform_fee_minor'])]);
            fputcsv($out, ['VAT accrued', NgnMoney::csvMajor((int) $payload['period']['vat_minor'])]);
            fputcsv($out, ['Released to freelancers', NgnMoney::csvMajor((int) $payload['period']['released_to_freelancers_minor'])]);
            fputcsv($out, ['Refunded', NgnMoney::csvMajor((int) $payload['period']['refunded_minor'])]);
            fputcsv($out, []);
            fputcsv($out, ['Escrow held as of', $payload['as_of_position']['as_of_label']]);
            fputcsv($out, ['Total held', NgnMoney::csvMajor((int) $payload['as_of_position']['total_held_minor'])]);
            fputcsv($out, ['Active contracts', $payload['as_of_position']['active_count']]);
            fputcsv($out, []);
            fputcsv($out, [
                'Escrow ref', 'Contract ref', 'Quest title', 'Client', 'Freelancer',
                'Status', 'Funded', 'Due', 'Amount held (NGN)', 'Platform fee (NGN)', 'VAT (NGN)', 'Freelancer net (NGN)',
            ]);
            foreach ($payload['as_of_position']['contracts'] as $row) {
                fputcsv($out, [
                    $row['escrow_reference'],
                    $row['contract_reference'],
                    $row['quest_title'],
                    $row['client_name'],
                    $row['freelancer_name'],
                    $row['status_label'],
                    $row['funded_at'],
                    $row['due_date_label'],
                    NgnMoney::csvMajor((int) ($row['funded_minor'] ?? 0)),
                    NgnMoney::csvMajor((int) ($row['platform_fee_minor'] ?? 0)),
                    NgnMoney::csvMajor((int) ($row['vat_minor'] ?? 0)),
                    NgnMoney::csvMajor((int) ($row['freelancer_net_minor'] ?? 0)),
                ]);
            }
            fclose($out);
        }, $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function contractReport(Request $request, string $type): array
    {
        [$from, $to] = $this->resolveRange($request);
        $query = $this->contractQuery($request, $from, $to);
        $totalsQuery = clone $query;

        $paginator = $query->paginate(25)->withQueryString();
        $rows = collect($paginator->items())->map(fn (FinancialEscrowRecord $r) => $this->contractReportRow($r, $type));

        if ($type === 'vat') {
            $cumulative = 0;
            $rows = $rows->map(function (array $row) use (&$cumulative) {
                $cumulative += (int) $row['vat_minor'];
                $row['cumulative_vat_minor'] = $cumulative;
                $row['cumulative_vat_display'] = NgnMoney::format($cumulative);

                return $row;
            });
        }

        $totals = $totalsQuery->selectRaw('
            count(*) as row_count,
            coalesce(sum(total_funded_minor), 0) as gross_minor,
            coalesce(sum(platform_fee_minor), 0) as fee_minor,
            coalesce(sum(vat_minor), 0) as vat_minor,
            coalesce(sum(platform_fee_minor - vat_minor), 0) as revenue_minor
        ')->first();

        $totalsPayload = $type === 'vat'
            ? [
                'count' => (int) ($totals->row_count ?? 0),
                'gross_display' => NgnMoney::format((int) ($totals->gross_minor ?? 0)),
                'vat_display' => NgnMoney::format((int) ($totals->vat_minor ?? 0)),
            ]
            : [
                'count' => (int) ($totals->row_count ?? 0),
                'gross_display' => NgnMoney::format((int) ($totals->gross_minor ?? 0)),
                'revenue_display' => NgnMoney::format(max(0, (int) ($totals->revenue_minor ?? 0))),
            ];

        return [
            'type' => $type,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'date_field' => (string) $request->query('date_field', 'funded_at'),
                'status' => (string) $request->query('status', ''),
                'sort' => (string) $request->query('sort', 'funded_at'),
                'dir' => (string) $request->query('dir', 'desc'),
                'label' => $from->format('d M Y').' – '.$to->format('d M Y'),
            ],
            'statuses' => ['held', 'released', 'refunded', 'partially_released', 'disputed'],
            'date_fields' => [
                ['value' => 'funded_at', 'label' => 'Funded date'],
                ['value' => 'released_at', 'label' => 'Release date'],
                ['value' => 'fee_recognised_at', 'label' => 'Fee recognised date'],
            ],
            'totals' => $totalsPayload,
            'data' => $rows->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function contractQuery(Request $request, Carbon $from, Carbon $to): Builder
    {
        $dateField = (string) $request->query('date_field', 'funded_at');
        if (! in_array($dateField, ['funded_at', 'released_at', 'fee_recognised_at'], true)) {
            $dateField = 'funded_at';
        }

        $query = FinancialEscrowRecord::query()
            ->with(['contract:id,reference_code,agreed_delivery_date', 'category:id,name']);

        if ($request->filled('from') || $request->filled('to') || ! $request->has('all_time')) {
            $query->whereNotNull($dateField)
                ->whereBetween($dateField, [$from, $to]);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('escrow_reference', 'like', "%{$search}%")
                    ->orWhere('contract_reference', 'like', "%{$search}%")
                    ->orWhere('quest_title', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('freelancer_name', 'like', "%{$search}%");
            });
        }

        $sort = (string) $request->query('sort', 'funded_at');
        $dir = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowed = ['funded_at', 'released_at', 'fee_recognised_at', 'quest_title', 'contract_reference', 'status', 'vat_minor', 'platform_fee_minor', 'total_funded_minor'];
        if (in_array($sort, $allowed, true)) {
            $query->orderBy($sort, $dir);
        } else {
            $query->orderByDesc('funded_at');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function contractReportRow(FinancialEscrowRecord $r, string $type): array
    {
        $platformRevenue = max(0, (int) $r->platform_fee_minor - (int) $r->vat_minor);
        $isRecognised = in_array($r->status, ['released', 'partially_released'], true);

        $row = [
            'id' => $r->id,
            'escrow_reference' => $r->escrow_reference,
            'contract_reference' => $r->contract_reference,
            'quest_title' => $r->quest_title,
            'category' => $r->category?->name,
            'client_name' => $r->client_name,
            'freelancer_name' => $r->freelancer_name,
            'status' => $r->status,
            'status_label' => $r->statusEnum()->label(),
            'funded_at' => $r->funded_at?->toIso8601String(),
            'released_at' => $r->released_at?->toIso8601String(),
            'fee_recognised_at' => $r->fee_recognised_at?->toIso8601String(),
            'gross_display' => NgnMoney::format((int) $r->gross_contract_value_minor),
            'gross_minor' => (int) $r->gross_contract_value_minor,
            'platform_fee_display' => NgnMoney::format((int) $r->platform_fee_minor),
            'platform_fee_minor' => (int) $r->platform_fee_minor,
            'platform_revenue_display' => NgnMoney::format($platformRevenue),
            'platform_revenue_minor' => $platformRevenue,
            'vat_percent' => (float) $r->vat_percent,
            'vat_display' => NgnMoney::format((int) $r->vat_minor),
            'vat_minor' => (int) $r->vat_minor,
            'freelancer_net_display' => NgnMoney::format((int) $r->freelancer_net_minor),
            'vat_status' => $isRecognised ? 'Recognised' : 'Projected',
        ];

        if ($type === 'platform_fee' && $isRecognised) {
            $ledgerRevenue = (int) LedgerEntry::query()
                ->where('payment_escrow_id', $r->payment_escrow_id)
                ->where('ledger_account', 'platform_fee_revenue')
                ->where('side', 'credit')
                ->sum('amount_minor');
            if ($ledgerRevenue > 0) {
                $row['platform_revenue_minor'] = $ledgerRevenue;
                $row['platform_revenue_display'] = NgnMoney::format($ledgerRevenue);
            }
        }

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function revenueSources(): array
    {
        return [
            [
                'key' => 'escrow_fees',
                'label' => 'Escrow fees',
                'description' => 'Platform fee on escrow release',
                'available' => true,
            ],
            [
                'key' => 'quest_boosts',
                'label' => 'Quest boosts',
                'description' => 'Admin-granted boost promotional investment',
                'available' => true,
            ],
            [
                'key' => 'premium_freelancers',
                'label' => 'Premium freelancers',
                'description' => 'Freelancer Pro subscription revenue',
                'available' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenueSummary(Carbon $from, Carbon $to): array
    {
        $escrowRevenue = (int) \App\Models\LedgerEntry::query()
            ->where('ledger_account', 'platform_fee_revenue')
            ->where('side', 'credit')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('amount_minor');

        $questBoosts = (int) \App\Models\QuestBoostPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_minor');

        $premiumFreelancers = (int) \App\Models\FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_minor');
        $total = $escrowRevenue + $questBoosts + $premiumFreelancers;

        return [
            'escrow_fees_minor' => $escrowRevenue,
            'escrow_fees_display' => NgnMoney::format($escrowRevenue),
            'quest_boosts_minor' => $questBoosts,
            'quest_boosts_display' => NgnMoney::format($questBoosts),
            'premium_freelancers_minor' => $premiumFreelancers,
            'premium_freelancers_display' => NgnMoney::format($premiumFreelancers),
            'total_minor' => $total,
            'total_display' => NgnMoney::format($total),
        ];
    }

    private function exportContractReportCsv(Request $request, string $type): StreamedResponse
    {
        $request->merge(['page' => 1]);
        [$from, $to] = $this->resolveRange($request);
        $rows = $this->contractQuery($request, $from, $to)
            ->limit(5000)
            ->get()
            ->map(fn (FinancialEscrowRecord $r) => $this->contractReportRow($r, $type));

        $filename = $type.'-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows, $type): void {
            // Excel on Windows opens CSV as Windows-1252 by default; UTF-8 BOM helps text columns.
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            if ($type === 'vat') {
                fputcsv($out, [
                    'Funded date', 'Release date', 'Escrow ref', 'Contract ref', 'Client', 'Freelancer',
                    'Status', 'Gross (NGN)', 'VAT rate %', 'VAT (NGN)', 'VAT status', 'Cumulative VAT (NGN)',
                ]);
                $cumulative = 0;
                foreach ($rows as $row) {
                    $cumulative += (int) $row['vat_minor'];
                    fputcsv($out, [
                        $row['funded_at'] ? Carbon::parse($row['funded_at'])->format('Y-m-d') : '',
                        $row['released_at'] ? Carbon::parse($row['released_at'])->format('Y-m-d') : '',
                        $row['escrow_reference'],
                        $row['contract_reference'],
                        $row['client_name'],
                        $row['freelancer_name'],
                        $row['status_label'],
                        NgnMoney::csvMajor((int) $row['gross_minor']),
                        $row['vat_percent'],
                        NgnMoney::csvMajor((int) $row['vat_minor']),
                        $row['vat_status'],
                        NgnMoney::csvMajor($cumulative),
                    ]);
                }
            } else {
                fputcsv($out, [
                    'Funded date', 'Release date', 'Fee recognised', 'Escrow ref', 'Contract ref',
                    'Client', 'Freelancer', 'Status', 'Gross (NGN)', 'Gross fee (NGN)', 'Net revenue (NGN)',
                ]);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['funded_at'] ? Carbon::parse($row['funded_at'])->format('Y-m-d') : '',
                        $row['released_at'] ? Carbon::parse($row['released_at'])->format('Y-m-d') : '',
                        $row['fee_recognised_at'] ? Carbon::parse($row['fee_recognised_at'])->format('Y-m-d') : '',
                        $row['escrow_reference'],
                        $row['contract_reference'],
                        $row['client_name'],
                        $row['freelancer_name'],
                        $row['status_label'],
                        NgnMoney::csvMajor((int) $row['gross_minor']),
                        NgnMoney::csvMajor((int) $row['platform_fee_minor']),
                        NgnMoney::csvMajor((int) $row['platform_revenue_minor']),
                    ]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function contractRowsToCsv(string $type, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($type === 'vat') {
            fputcsv($handle, [
                'Funded date', 'Release date', 'Escrow ref', 'Contract ref', 'Client', 'Freelancer',
                'Status', 'Gross (NGN)', 'VAT rate %', 'VAT (NGN)', 'VAT status', 'Cumulative VAT (NGN)',
            ]);
            $cumulative = 0;
            foreach ($rows as $row) {
                $cumulative += (int) $row['vat_minor'];
                fputcsv($handle, [
                    $row['funded_at'] ? Carbon::parse($row['funded_at'])->format('Y-m-d') : '',
                    $row['released_at'] ? Carbon::parse($row['released_at'])->format('Y-m-d') : '',
                    $row['escrow_reference'],
                    $row['contract_reference'],
                    $row['client_name'],
                    $row['freelancer_name'],
                    $row['status_label'],
                    NgnMoney::csvMajor((int) $row['gross_minor']),
                    $row['vat_percent'],
                    NgnMoney::csvMajor((int) $row['vat_minor']),
                    $row['vat_status'],
                    NgnMoney::csvMajor($cumulative),
                ]);
            }
        } else {
            fputcsv($handle, [
                'Funded date', 'Release date', 'Fee recognised', 'Escrow ref', 'Contract ref',
                'Client', 'Freelancer', 'Status', 'Gross (NGN)', 'Gross fee (NGN)', 'Net revenue (NGN)',
            ]);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['funded_at'] ? Carbon::parse($row['funded_at'])->format('Y-m-d') : '',
                    $row['released_at'] ? Carbon::parse($row['released_at'])->format('Y-m-d') : '',
                    $row['fee_recognised_at'] ? Carbon::parse($row['fee_recognised_at'])->format('Y-m-d') : '',
                    $row['escrow_reference'],
                    $row['contract_reference'],
                    $row['client_name'],
                    $row['freelancer_name'],
                    $row['status_label'],
                    NgnMoney::csvMajor((int) $row['gross_minor']),
                    NgnMoney::csvMajor((int) $row['platform_fee_minor']),
                    NgnMoney::csvMajor((int) $row['platform_revenue_minor']),
                ]);
            }
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $dashboard = app(FinancialAuditDashboardService::class);

        if ($request->boolean('all_time')) {
            $first = FinancialEscrowRecord::query()->min('funded_at');

            return [
                $first ? Carbon::parse($first)->startOfDay() : now()->startOfYear(),
                now()->endOfDay(),
            ];
        }

        return $dashboard->resolvePeriod($request);
    }

    public function exportEscrowLedger(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $format = (string) $request->query('format', 'csv');
        $dashboard = app(FinancialAuditDashboardService::class);
        $rows = $dashboard->escrowLedgerExportRows($request);
        $from = $request->query('from', 'all');
        $to = $request->query('to', 'all');
        $filename = 'escrow-ledger-'.$from.'-to-'.$to.'-'.now()->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.escrow-ledger-export', [
                'rows' => $rows,
                'from' => $from,
                'to' => $to,
                'generated_at' => now(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename.'.pdf');
        }

        return response()->streamDownload(function () use ($rows): void {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Escrow reference', 'Contract reference', 'Quest title', 'Client', 'Freelancer',
                'Gross (NGN)', 'Platform fee (NGN)', 'VAT (NGN)', 'Freelancer net (NGN)', 'Status', 'Funded at', 'Released at', 'Paystack reference',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['escrow_reference'],
                    $row['contract_reference'],
                    $row['quest_title'],
                    $row['client_name'],
                    $row['freelancer_name'],
                    NgnMoney::csvMajor((int) ($row['gross_minor'] ?? 0)),
                    NgnMoney::csvMajor((int) ($row['platform_fee_minor'] ?? 0)),
                    NgnMoney::csvMajor((int) ($row['vat_minor'] ?? 0)),
                    NgnMoney::csvMajor((int) ($row['freelancer_net_minor'] ?? 0)),
                    $row['status'],
                    $row['funded_at'],
                    $row['released_at'],
                    $row['paystack_reference'],
                ]);
            }
            fclose($out);
        }, $filename.'.csv');
    }
}
