<?php

namespace App\Services\Admin;

use App\Enums\FinancialEscrowRecordStatus;
use App\Enums\LedgerAccount;
use App\Enums\ReconciliationExceptionStatus;
use App\Models\FinancialEscrowRecord;
use App\Models\FinancialReconciliationException;
use App\Models\FinancialReconciliationRun;
use App\Models\LedgerEntry;
use App\Models\QuestContract;
use App\Models\VatRemittance;
use App\Services\Finance\DoubleEntryLedgerService;
use App\Support\EscrowAutoReleasePolicy;
use App\Support\NgnMoney;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FinancialHealthDashboardService
{
    public function __construct(
        private readonly DoubleEntryLedgerService $ledger,
        private readonly FinancialHealthChartService $charts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request): array
    {
        $period = $this->resolvePeriodKey($request);
        [$start, $end] = $this->periodRange($period, $request);
        $txnPage = max(1, (int) $request->query('page', 1));

        $metrics = $this->cachedMetrics($period, $request);
        $charts = $this->charts->build($request);
        $alerts = $this->buildAlerts();
        $paymentStatus = [
            'today' => $this->paymentStatusBreakdown('today'),
            'next_7_days' => $this->paymentStatusBreakdown('next_7_days'),
            'month' => $this->paymentStatusBreakdown('month'),
        ];
        $transactions = $this->transactionsTable($period, $request, $txnPage);
        $reconciliation = $this->reconciliationPanels();

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period, $request),
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
            'period_presets' => $this->presetOptions('period_presets'),
            'generated_at' => now()->toIso8601String(),
            'cache' => [
                'metrics_ttl_seconds' => (int) config('financial_health_dashboard.cache.metrics_ttl_seconds', 300),
                'charts_ttl_seconds' => (int) config('financial_health_dashboard.cache.charts_ttl_seconds', 3600),
            ],
            'kpis' => $metrics['kpis'],
            'alerts' => $alerts,
            'payment_status' => $paymentStatus,
            'charts' => $charts,
            'chart_grain' => (string) $request->query('chart_grain', 'daily'),
            'state_id' => (string) $request->query('state_id', 'all'),
            'states' => $this->charts->stateOptions(),
            'transactions' => $transactions,
            'reconciliation' => $reconciliation,
            'links' => [
                'reconcile' => route('admin.financial-audit.reconciliation.index'),
                'ledger' => route('admin.financial-audit.escrow-ledger'),
                'vat_report' => route('admin.financial-audit.reports.vat'),
                'exceptions' => route('admin.financial-audit.exceptions.index'),
                'export_csv' => route('admin.financial-health.export.csv', $this->exportQuery($request)),
                'export_pdf' => route('admin.financial-health.export.pdf', $this->exportQuery($request)),
            ],
        ];
    }

    public function warmCache(string $period = 'today'): void
    {
        Cache::forget($this->metricsCacheKey($period));
        $this->cachedMetrics($period);
    }

    public function exportPdf(Request $request)
    {
        $period = $this->resolvePeriodKey($request);
        [$start, $end] = $this->periodRange($period, $request);
        $payload = $this->indexPayload($request);
        $rows = $this->transactionsTable($period, $request, 1, perPage: 500)['data'];
        $filename = 'financial-health-'.$start->format('Ymd').'-'.$end->format('Ymd').'.pdf';

        return Pdf::loadView('pdf.financial-health-report', [
            'period_label' => $payload['period_label'],
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
            'kpis' => $payload['kpis'],
            'alerts' => $payload['alerts'],
            'reconciliation' => $payload['reconciliation'],
            'transactions' => $rows,
            'generated_at' => now(),
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    public function apiSnapshot(Request $request): array
    {
        $period = $this->resolvePeriodKey($request);

        return [
            'generated_at' => now()->toIso8601String(),
            'kpis' => $this->cachedMetrics($period, $request)['kpis'],
            'alerts' => $this->buildAlerts(),
        ];
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $period = $this->resolvePeriodKey($request);
        $rows = $this->transactionsTable($period, $request, 1, perPage: 5000)['data'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Transaction ID', 'Type', 'Amount', 'Status', 'Due date', 'Days overdue', 'Direction']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['type_label'],
                    $row['amount_display'],
                    $row['status_label'],
                    $row['due_date'] ?? '',
                    $row['days_overdue'] ?? '',
                    $row['direction'],
                ]);
            }
            fclose($out);
        }, 'financial-health-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function cachedMetrics(string $period, ?Request $request = null): array
    {
        $ttl = (int) config('financial_health_dashboard.cache.metrics_ttl_seconds', 300);

        return Cache::remember($this->metricsCacheKey($period, $request), $ttl, fn () => [
            'kpis' => $this->buildKpis($period, $request),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKpis(string $period, ?Request $request = null): array
    {
        [$start, $end] = $this->periodRange($period, $request);
        [$prevStart, $prevEnd] = $this->previousPeriodRange($period, $request);

        $escrowFunded = $this->escrowFundedBetween($start, $end);
        $escrowPrev = $this->escrowFundedBetween($prevStart, $prevEnd);
        $feeRevenue = $this->feeRevenueBetween($start, $end);
        $feePrev = $this->feeRevenueBetween($prevStart, $prevEnd);
        $feeCount = $this->feeTransactionCountBetween($start, $end);
        $vatCollected = $this->vatAccruedBetween($start, $end);
        $vatPrev = $this->vatAccruedBetween($prevStart, $prevEnd);
        $processorCosts = $this->processorCostsMinor($feeRevenue);
        $netRevenue = max(0, $feeRevenue - $processorCosts);
        $netPrev = max(0, $feePrev - $this->processorCostsMinor($feePrev));
        $budgetMinor = (int) config('financial_health_dashboard.monthly_revenue_budget_minor', 0);
        $budgetVariance = in_array($period, ['month', 'custom'], true) ? $netRevenue - $budgetMinor : null;

        $vatRemittable = $this->currentVatOutstanding();

        return [
            'escrow_funded' => $this->kpiCard(
                'Escrow funded',
                $escrowFunded['total_minor'],
                $escrowFunded['count'],
                'contracts',
                $escrowFunded['total_minor'] - $escrowPrev['total_minor'],
            ),
            'platform_fee' => $this->kpiCard(
                'Platform fee generated',
                $feeRevenue,
                $feeCount,
                'transactions',
                $feeRevenue - $feePrev,
            ),
            'vat_collected' => array_merge(
                $this->kpiCard(
                    'VAT collected',
                    $vatCollected,
                    null,
                    null,
                    $vatCollected - $vatPrev,
                ),
                [
                    'remittable_minor' => $vatRemittable,
                    'remittable_display' => NgnMoney::format($vatRemittable),
                    'remittance_status' => $this->vatRemittanceStatus(),
                    'vat_percent' => (float) config('financial_health_dashboard.vat_percent', 7.5),
                ],
            ),
            'net_revenue' => array_merge(
                $this->kpiCard(
                    'Net revenue',
                    $netRevenue,
                    null,
                    null,
                    $netRevenue - $netPrev,
                ),
                [
                    'processor_costs_minor' => $processorCosts,
                    'processor_costs_display' => NgnMoney::format($processorCosts),
                    'budget_minor' => $budgetMinor,
                    'budget_display' => NgnMoney::format($budgetMinor),
                    'budget_variance_minor' => $budgetVariance,
                    'budget_variance_display' => $budgetVariance !== null ? NgnMoney::format(abs($budgetVariance)) : null,
                    'budget_on_track' => $budgetVariance === null ? null : $budgetVariance >= 0,
                ],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpiCard(
        string $label,
        int $totalMinor,
        ?int $count,
        ?string $countLabel,
        int $trendDeltaMinor,
    ): array {
        return [
            'label' => $label,
            'total_minor' => $totalMinor,
            'total_display' => NgnMoney::format($totalMinor),
            'count' => $count,
            'count_label' => $countLabel,
            'trend_delta_minor' => $trendDeltaMinor,
            'trend_delta_display' => NgnMoney::format(abs($trendDeltaMinor)),
            'trend_direction' => $trendDeltaMinor > 0 ? 'up' : ($trendDeltaMinor < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAlerts(): array
    {
        $items = [];
        $cfg = config('financial_health_dashboard.alerts', []);

        $overdue = $this->overdueReleases();
        if ($overdue['count'] > 0) {
            $items[] = [
                'severity' => 'critical',
                'key' => 'overdue_releases',
                'icon' => 'overdue',
                'message' => __('Overdue releases: :count payments (:amount total) overdue by :min–:max days', [
                    'count' => $overdue['count'],
                    'amount' => NgnMoney::format($overdue['total_minor']),
                    'min' => $overdue['min_days'],
                    'max' => $overdue['max_days'],
                ]),
                'action_label' => 'View details',
                'action_url' => route('admin.financial-audit.escrow-ledger', ['status' => 'held']),
            ];
        }

        $position = $this->escrowPosition();
        $variance = abs((int) ($position['variance_minor'] ?? 0));
        $criticalVariance = (int) ($cfg['escrow_variance_critical_minor'] ?? 500_000);
        if ($variance >= $criticalVariance) {
            $items[] = [
                'severity' => 'critical',
                'key' => 'escrow_imbalance',
                'icon' => 'imbalance',
                'message' => __('Escrow imbalance: :amount variance detected (last reconciled :ago)', [
                    'amount' => NgnMoney::format($variance),
                    'ago' => $position['last_reconciled_ago'] ?? 'unknown',
                ]),
                'action_label' => 'Reconcile now',
                'action_url' => route('admin.financial-audit.reconciliation.index'),
            ];
        } elseif ($variance >= (int) ($cfg['escrow_variance_warning_minor'] ?? 100_000)) {
            $items[] = [
                'severity' => 'high',
                'key' => 'escrow_imbalance',
                'icon' => 'imbalance',
                'message' => __('Escrow variance: :amount — review reconciliation', [
                    'amount' => NgnMoney::format($variance),
                ]),
                'action_label' => 'Reconcile',
                'action_url' => route('admin.financial-audit.reconciliation.index'),
            ];
        }

        $vatDue = $this->vatRemittanceDeadline();
        if ($vatDue !== null) {
            $daysLeft = (int) $vatDue['days_remaining'];
            $criticalDays = (int) ($cfg['vat_remittance_critical_days'] ?? 3);
            $warningDays = (int) ($cfg['vat_remittance_warning_days'] ?? 7);
            if ($daysLeft <= $warningDays) {
                $items[] = [
                    'severity' => $daysLeft <= $criticalDays ? 'critical' : 'high',
                    'key' => 'vat_remittance',
                    'icon' => 'vat',
                    'message' => __('VAT remittance due: :amount due to NRS by :date (:days days remaining)', [
                        'amount' => NgnMoney::format($vatDue['amount_minor']),
                        'date' => $vatDue['deadline_label'],
                        'days' => max(0, $daysLeft),
                    ]),
                    'action_label' => 'Schedule payment',
                    'action_url' => route('admin.financial-audit.reports.vat'),
                ];
            }
        }

        $autoFail = $this->autoReleaseFailures();
        if ($autoFail['count'] > 0) {
            $items[] = [
                'severity' => 'critical',
                'key' => 'auto_release_failing',
                'icon' => 'processor',
                'message' => __('Auto-release failing: :count contracts need investigation', [
                    'count' => $autoFail['count'],
                ]),
                'action_label' => 'Investigate',
                'action_url' => route('admin.financial-audit.exceptions.index'),
            ];
        }

        $dueSoon = $this->paymentsDueWithinHours((int) ($cfg['payment_due_soon_hours'] ?? 24));
        if ($dueSoon['count'] > 0) {
            $items[] = [
                'severity' => 'high',
                'key' => 'payment_due_soon',
                'icon' => 'clock',
                'message' => __(':count payments (:amount) due within 24 hours', [
                    'count' => $dueSoon['count'],
                    'amount' => NgnMoney::format($dueSoon['total_minor']),
                ]),
                'action_label' => 'View schedule',
                'action_url' => route('admin.financial-health.index', ['payment_horizon' => 'today']),
            ];
        }

        if ($this->noTransactionsToday()) {
            $items[] = [
                'severity' => 'medium',
                'key' => 'no_transactions_today',
                'icon' => 'info',
                'message' => __('No escrow fundings recorded today yet.'),
                'action_label' => null,
                'action_url' => null,
            ];
        }

        $pendingWithdrawals = $this->pendingWithdrawalsCount();
        if ($pendingWithdrawals > 0) {
            $items[] = [
                'severity' => 'medium',
                'key' => 'withdrawals_pending',
                'icon' => 'wallet',
                'message' => __(':count freelancer withdrawal(s) pending review', ['count' => $pendingWithdrawals]),
                'action_label' => 'View treasury',
                'action_url' => route('admin.treasury.index'),
            ];
        }

        return [
            'has_critical' => collect($items)->contains(fn ($i) => $i['severity'] === 'critical'),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentStatusBreakdown(string $horizon): array
    {
        $now = now();
        [$from, $to] = match ($horizon) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'next_7_days' => [$now->copy()->startOfDay(), $now->copy()->addDays(7)->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        $rows = $this->scheduledReleasesBetween($from, $to);
        $totalMinor = (int) $rows->sum('amount_minor');
        $onHold = $rows->where('status_key', 'on_hold')->count();
        $awaiting = $rows->where('status_key', 'awaiting')->count();
        $queued = $rows->where('status_key', 'queued')->count();
        $atRisk = $rows->where('auto_release_pending', true)->count();

        $statusSummary = match (true) {
            $onHold > 0 => 'On hold',
            $awaiting > 0 && $queued > 0 => 'Awaiting approval / Queued',
            $awaiting > 0 => 'Awaiting approval',
            $queued > 0 => 'Queued',
            default => 'Ready',
        };

        return [
            'horizon' => $horizon,
            'count' => $rows->count(),
            'amount_minor' => $totalMinor,
            'amount_display' => NgnMoney::format($totalMinor),
            'status_summary' => $statusSummary,
            'breakdown' => [
                'awaiting_approval' => $awaiting,
                'queued' => $queued,
                'on_hold' => $onHold,
                'scheduled' => $rows->where('status_key', 'scheduled')->count(),
            ],
            'at_risk_count' => $atRisk,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionsTable(string $period, Request $request, int $page, int $perPage = 50): array
    {
        [$start, $end] = $this->periodRange($period, $request);
        $statusFilter = $request->query('txn_status');
        $typeFilter = $request->query('txn_type');
        $directionFilter = $request->query('txn_direction');

        $escrowRows = FinancialEscrowRecord::query()
            ->with([
                'contract:id,agreed_delivery_date,reference_code,deadline_clock_paused_at',
                'quest:id,release_hold_reason,release_hold_until',
            ])
            ->whereBetween('funded_at', [$start, $end])
            ->orderByDesc('funded_at')
            ->limit(500)
            ->get()
            ->map(fn (FinancialEscrowRecord $r) => $this->mapEscrowTransaction($r));

        $vatRows = collect();
        if (Schema::hasTable('vat_remittances')) {
            $vatRows = VatRemittance::query()
                ->whereBetween('created_at', [$start, $end])
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn (VatRemittance $v) => [
                    'id' => 'VAT-'.$v->id,
                    'type' => 'vat',
                    'type_label' => 'VAT remittance',
                    'amount_minor' => (int) $v->amount_minor,
                    'amount_display' => NgnMoney::format((int) $v->amount_minor),
                    'status' => 'scheduled',
                    'status_label' => 'Scheduled',
                    'status_tone' => 'amber',
                    'due_date' => $v->remitted_at?->toDateString(),
                    'days_overdue' => null,
                    'direction' => 'outflow',
                    'contract_reference' => null,
                    'action_url' => route('admin.financial-audit.reports.vat'),
                    'action_label' => 'Schedule',
                ]);
        }

        $merged = $escrowRows->concat($vatRows)->sortByDesc(fn ($r) => $r['sort_at'] ?? '')->values();

        if ($statusFilter && $statusFilter !== 'all') {
            $merged = $merged->filter(fn ($r) => $r['status'] === $statusFilter)->values();
        }
        if ($typeFilter && $typeFilter !== 'all') {
            $merged = $merged->filter(fn ($r) => $r['type'] === $typeFilter)->values();
        }
        if ($directionFilter && $directionFilter !== 'all') {
            $merged = $merged->filter(fn ($r) => $r['direction'] === $directionFilter)->values();
        }

        $total = $merged->count();
        $data = $merged->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'filters' => [
                'status' => $statusFilter ?? 'all',
                'type' => $typeFilter ?? 'all',
                'direction' => $directionFilter ?? 'all',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEscrowTransaction(FinancialEscrowRecord $r): array
    {
        $due = $r->contract?->agreed_delivery_date;
        $daysOverdue = null;
        $status = 'funded';
        $statusLabel = 'Funded';
        $statusTone = 'emerald';

        if ($r->status === FinancialEscrowRecordStatus::Held->value && $due) {
            $releaseAt = EscrowAutoReleasePolicy::releaseAt(Carbon::parse($due)->endOfDay());
            if (now()->gt($releaseAt)) {
                $status = 'overdue';
                $statusLabel = 'OVERDUE';
                $statusTone = 'rose';
                $daysOverdue = (int) $releaseAt->diffInDays(now());
            } else {
                $status = 'awaiting';
                $statusLabel = 'Pending';
                $statusTone = 'amber';
            }
        } elseif (in_array($r->status, [FinancialEscrowRecordStatus::Released->value, FinancialEscrowRecordStatus::PartiallyReleased->value], true)) {
            $status = 'released';
            $statusLabel = 'Released';
            $statusTone = 'emerald';
        } elseif ($r->status === FinancialEscrowRecordStatus::Disputed->value) {
            $status = 'on_hold';
            $statusLabel = 'On hold';
            $statusTone = 'orange';
        }

        return [
            'id' => $r->contract_reference ?: $r->escrow_reference,
            'record_id' => $r->id,
            'quest_id' => $r->quest_id,
            'type' => 'escrow',
            'type_label' => $r->released_at ? 'Release' : 'Escrow funded',
            'amount_minor' => (int) ($r->released_at ? $r->freelancer_net_minor : $r->total_funded_minor),
            'amount_display' => NgnMoney::format((int) ($r->released_at ? $r->freelancer_net_minor : $r->total_funded_minor)),
            'status' => $status,
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'due_date' => $due?->format('j M Y'),
            'days_overdue' => $daysOverdue,
            'direction' => $r->released_at ? 'outflow' : 'inflow',
            'contract_reference' => $r->contract_reference,
            'sort_at' => ($r->released_at ?? $r->funded_at)?->toIso8601String(),
            'on_hold' => (bool) ($r->quest?->release_hold_reason || $status === 'on_hold'),
            'can_hold' => $r->status === FinancialEscrowRecordStatus::Held->value && ! $r->quest?->release_hold_reason,
            'can_lift_hold' => (bool) $r->quest?->release_hold_reason,
            'can_investigate' => $r->id !== null,
            'action_url' => $r->id ? route('admin.financial-audit.escrow-records.show', $r) : null,
            'action_label' => $status === 'overdue' ? 'Release now' : 'View',
            'routes' => $r->id ? [
                'note' => route('admin.financial-health.transactions.note', $r),
                'hold' => route('admin.financial-health.transactions.hold', $r),
                'lift_hold' => route('admin.financial-health.transactions.lift-hold', $r),
                'investigate' => route('admin.financial-health.transactions.investigate', $r),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reconciliationPanels(): array
    {
        $position = $this->escrowPosition();
        $variance = (int) ($position['variance_minor'] ?? 0);
        $lastRun = FinancialReconciliationRun::query()->latest('id')->first();
        $monthStart = now()->startOfMonth();
        $vatCollected = $this->vatAccruedBetween($monthStart, now());
        $vatRemitted = (int) VatRemittance::query()
            ->where('period_start', '>=', $monthStart->toDateString())
            ->sum('amount_minor');
        $vatOutstanding = max(0, $vatCollected - $vatRemitted);
        $vatDeadline = $this->vatRemittanceDeadline();

        return [
            'escrow' => [
                'system_held_minor' => $position['total_held_minor'],
                'system_held_display' => $position['total_held_display'],
                'bank_balance_minor' => $position['ledger_liability_minor'],
                'bank_balance_display' => $position['ledger_liability_display'],
                'variance_minor' => $variance,
                'variance_display' => NgnMoney::format(abs($variance)),
                'balanced' => $variance === 0,
                'last_reconciled_at' => $lastRun?->finished_at?->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                'last_reconciled_ago' => $lastRun?->finished_at?->diffForHumans(),
            ],
            'vat' => [
                'collected_minor' => $vatCollected,
                'collected_display' => NgnMoney::format($vatCollected),
                'remitted_minor' => $vatRemitted,
                'remitted_display' => NgnMoney::format($vatRemitted),
                'outstanding_minor' => $vatOutstanding,
                'outstanding_display' => NgnMoney::format($vatOutstanding),
                'deadline_label' => $vatDeadline['deadline_label'] ?? null,
                'period_label' => now()->format('M Y'),
            ],
        ];
    }

    /**
     * @return array{total_minor: int, count: int}
     */
    private function escrowFundedBetween(Carbon $start, Carbon $end): array
    {
        $q = FinancialEscrowRecord::query()->whereBetween('funded_at', [$start, $end]);

        return [
            'total_minor' => (int) $q->sum('total_funded_minor'),
            'count' => (int) $q->count(),
        ];
    }

    private function feeRevenueBetween(Carbon $start, Carbon $end): int
    {
        return (int) LedgerEntry::query()
            ->where('ledger_account', LedgerAccount::PlatformFeeRevenue->value)
            ->where('side', 'credit')
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount_minor');
    }

    private function feeTransactionCountBetween(Carbon $start, Carbon $end): int
    {
        return (int) LedgerEntry::query()
            ->where('ledger_account', LedgerAccount::PlatformFeeRevenue->value)
            ->where('side', 'credit')
            ->whereBetween('occurred_at', [$start, $end])
            ->distinct('batch_id')
            ->count('batch_id');
    }

    private function vatAccruedBetween(Carbon $start, Carbon $end): int
    {
        return (int) LedgerEntry::query()
            ->where('ledger_account', LedgerAccount::VatPayable->value)
            ->where('side', 'credit')
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount_minor');
    }

    private function processorCostsMinor(int $feeRevenueMinor): int
    {
        $pct = (float) config('financial_health_dashboard.processor_fee_percent', 1.5);

        return (int) round($feeRevenueMinor * ($pct / 100));
    }

    private function currentVatOutstanding(): int
    {
        $monthStart = now()->startOfMonth();

        return max(0, $this->vatAccruedBetween($monthStart, now()) - (int) VatRemittance::query()
            ->where('period_start', '>=', $monthStart->toDateString())
            ->sum('amount_minor'));
    }

    private function vatRemittanceStatus(): string
    {
        $deadline = $this->vatRemittanceDeadline();
        if ($deadline === null) {
            return 'On track';
        }

        $days = (int) $deadline['days_remaining'];

        return $days <= 3 ? 'Due soon' : 'On track';
    }

    /**
     * @return array{amount_minor: int, deadline_label: string, days_remaining: int}|null
     */
    private function vatRemittanceDeadline(): ?array
    {
        $outstanding = $this->currentVatOutstanding();
        if ($outstanding <= 0) {
            return null;
        }

        $deadline = now()->endOfMonth()->addDays(21);
        if (now()->gt($deadline)) {
            $deadline = now()->addMonth()->endOfMonth()->addDays(21);
        }

        return [
            'amount_minor' => $outstanding,
            'deadline_label' => $deadline->format('j M Y'),
            'days_remaining' => (int) now()->diffInDays($deadline, false),
        ];
    }

    /**
     * @return array{count: int, total_minor: int, min_days: int, max_days: int}
     */
    private function overdueReleases(): array
    {
        $hours = (int) config('financial_health_dashboard.alerts.overdue_release_hours', 48);
        $records = FinancialEscrowRecord::query()
            ->with('contract:id,agreed_delivery_date')
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->get();

        $overdue = $records->filter(function (FinancialEscrowRecord $r) use ($hours) {
            $due = $r->contract?->agreed_delivery_date;
            if (! $due) {
                return false;
            }
            $releaseAt = EscrowAutoReleasePolicy::releaseAt(Carbon::parse($due)->endOfDay());

            return now()->gt($releaseAt->copy()->addHours($hours));
        });

        $days = $overdue->map(function (FinancialEscrowRecord $r) {
            $due = $r->contract?->agreed_delivery_date;

            return (int) EscrowAutoReleasePolicy::releaseAt(Carbon::parse($due)->endOfDay())->diffInDays(now());
        });

        return [
            'count' => $overdue->count(),
            'total_minor' => (int) $overdue->sum('freelancer_net_minor'),
            'min_days' => $days->min() ?? 0,
            'max_days' => $days->max() ?? 0,
        ];
    }

    /**
     * @return array{count: int}
     */
    private function autoReleaseFailures(): array
    {
        if (! Schema::hasTable('financial_reconciliation_exceptions')) {
            return ['count' => 0];
        }

        $count = FinancialReconciliationException::query()
            ->whereIn('status', [
                ReconciliationExceptionStatus::Open->value,
                ReconciliationExceptionStatus::UnderInvestigation->value,
            ])
            ->where(function ($query): void {
                $query->where('type', 'like', '%auto_release%')
                    ->orWhere('title', 'like', '%auto release%')
                    ->orWhere('description', 'like', '%auto release%');
            })
            ->count();

        return ['count' => $count];
    }

    /**
     * @return array<string, mixed>
     */
    private function escrowPosition(): array
    {
        $heldTotal = (int) FinancialEscrowRecord::query()
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->sum('total_funded_minor');
        $ledgerLiability = abs($this->ledger->accountBalanceMinor(LedgerAccount::ClientEscrowLiability));
        $lastRun = FinancialReconciliationRun::query()->latest('id')->first();

        return [
            'total_held_minor' => $heldTotal,
            'total_held_display' => NgnMoney::format($heldTotal),
            'ledger_liability_minor' => $ledgerLiability,
            'ledger_liability_display' => NgnMoney::format($ledgerLiability),
            'variance_minor' => $heldTotal - $ledgerLiability,
            'last_reconciled_ago' => $lastRun?->finished_at?->diffForHumans(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function scheduledReleasesBetween(Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return FinancialEscrowRecord::query()
            ->with('contract:id,agreed_delivery_date,deadline_clock_paused_at')
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->get()
            ->map(function (FinancialEscrowRecord $r) {
                $due = $r->contract?->agreed_delivery_date;
                if (! $due) {
                    return null;
                }
                $releaseAt = EscrowAutoReleasePolicy::releaseAt(Carbon::parse($due)->endOfDay());
                $paused = $r->contract?->deadline_clock_paused_at !== null;
                $overdue = now()->gt($releaseAt);

                return [
                    'amount_minor' => (int) $r->freelancer_net_minor,
                    'due_at' => $releaseAt,
                    'status_key' => $paused ? 'on_hold' : ($overdue ? 'queued' : 'awaiting'),
                    'auto_release_pending' => $overdue && ! $paused,
                ];
            })
            ->filter()
            ->filter(fn ($row) => $row['due_at']->between($from, $to))
            ->values();
    }

    /**
     * @return array{count: int, total_minor: int}
     */
    private function paymentsDueWithinHours(int $hours): array
    {
        $until = now()->addHours($hours);
        $rows = $this->scheduledReleasesBetween(now()->startOfDay(), $until);

        return [
            'count' => $rows->count(),
            'total_minor' => (int) $rows->sum('amount_minor'),
        ];
    }

    private function noTransactionsToday(): bool
    {
        return FinancialEscrowRecord::query()
            ->where('funded_at', '>=', now()->startOfDay())
            ->count() === 0;
    }

    private function pendingWithdrawalsCount(): int
    {
        if (! Schema::hasTable('wallet_withdrawals')) {
            return 0;
        }

        return (int) \App\Models\WalletWithdrawal::query()
            ->whereIn('status', ['pending', 'processing'])
            ->count();
    }

    private function resolvePeriodKey(Request $request): string
    {
        $key = (string) $request->query('period', 'today');

        if ($key === 'custom' && $request->filled('date_from') && $request->filled('date_to')) {
            return 'custom';
        }

        return array_key_exists($key, config('financial_health_dashboard.period_presets', [])) ? $key : 'today';
    }

    private function periodLabel(string $period, Request $request): string
    {
        if ($period === 'custom') {
            return $request->query('date_from').' to '.$request->query('date_to');
        }

        return config("financial_health_dashboard.period_presets.{$period}", ucfirst($period));
    }

    /**
     * @return array<string, string|null>
     */
    private function exportQuery(Request $request): array
    {
        $period = $this->resolvePeriodKey($request);

        return array_filter([
            'period' => $period,
            'date_from' => $period === 'custom' ? $request->query('date_from') : null,
            'date_to' => $period === 'custom' ? $request->query('date_to') : null,
            'txn_status' => $request->query('txn_status'),
            'txn_type' => $request->query('txn_type'),
            'txn_direction' => $request->query('txn_direction'),
        ], fn ($v) => $v !== null && $v !== '' && $v !== 'all');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(string $period, ?Request $request = null): array
    {
        $now = now();

        if ($period === 'custom' && $request?->filled('date_from') && $request->filled('date_to')) {
            return [
                Carbon::parse($request->query('date_from'))->startOfDay(),
                Carbon::parse($request->query('date_to'))->endOfDay(),
            ];
        }

        return match ($period) {
            'week' => [$now->copy()->startOfWeek(), $now],
            'month' => [$now->copy()->startOfMonth(), $now],
            default => [$now->copy()->startOfDay(), $now],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousPeriodRange(string $period, ?Request $request = null): array
    {
        if ($period === 'custom' && $request?->filled('date_from') && $request->filled('date_to')) {
            [$start, $end] = $this->periodRange($period, $request);
            $days = max(1, (int) $start->diffInDays($end) + 1);

            return [
                $start->copy()->subDays($days)->startOfDay(),
                $start->copy()->subDay()->endOfDay(),
            ];
        }

        $now = now();

        return match ($period) {
            'week' => [
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],
            'month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            default => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],
        };
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function presetOptions(string $configKey): array
    {
        return collect(config("financial_health_dashboard.{$configKey}", []))
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    private function metricsCacheKey(string $period, ?Request $request = null): string
    {
        if ($period === 'custom' && $request?->filled('date_from') && $request?->filled('date_to')) {
            return 'financial_health_dashboard:metrics:custom:'.$request->query('date_from').':'.$request->query('date_to');
        }

        return 'financial_health_dashboard:metrics:'.$period;
    }
}
