<?php

namespace App\Services\Admin;

use App\Enums\FinancialEscrowRecordStatus;
use App\Enums\LedgerAccount;
use App\Models\FinancialEscrowRecord;
use App\Models\FinancialReconciliationException;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\WalletWithdrawal;
use App\Services\Finance\DoubleEntryLedgerService;
use App\Services\Finance\FinancialAuditDashboardService;
use App\Services\Finance\FinancialReconciliationReportService;
use App\Support\EscrowAutoReleasePolicy;
use App\Support\NgnMoney;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class AdminEscrowManagementService
{
    public function __construct(
        private readonly FinancialAuditDashboardService $audit,
        private readonly DoubleEntryLedgerService $ledger,
        private readonly FinancialReconciliationReportService $reconciliation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Request $request): array
    {
        return [
            'position' => $this->positionStatement(),
            'metrics' => $this->metricsCards(),
            'balance_series' => $this->balanceSeries30d(),
            'release_waterfall' => $this->releaseWaterfall(),
            'health' => $this->healthGauges(),
            'alerts' => $this->alerts(),
            'listing' => $this->listing($request),
            'categories' => $this->audit->categoryFilterOptions(),
            'statuses' => collect(FinancialEscrowRecordStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->all(),
            'refreshed_at' => now()->timezone('Africa/Lagos')->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listing(Request $request): array
    {
        $request = $this->applyQuickViewToRequest($request);
        $base = $this->audit->escrowLedgerListing($request);
        $rows = collect($base['data'] ?? [])->map(fn (array $row) => $this->enrichTableRow($row));

        if ($quick = $request->query('quick_view')) {
            $rows = $this->filterRowsByQuickView($rows, (string) $quick);
        }

        if ($bucket = $request->query('due_bucket')) {
            $rows = $rows->filter(fn (array $row) => ($row['due_bucket'] ?? null) === $bucket)->values();
        }

        $base['data'] = $rows->all();
        $base['filters'] = array_merge($base['filters'] ?? [], $request->only([
            'quick_view', 'due_bucket', 'sort', 'direction',
        ]));

        return $base;
    }

    private function applyQuickViewToRequest(Request $request): Request
    {
        $quick = (string) $request->query('quick_view', '');
        if ($quick === 'disputed') {
            $request->merge(['status' => FinancialEscrowRecordStatus::Disputed->value]);
        }
        if ($quick === 'held') {
            $request->merge(['status' => FinancialEscrowRecordStatus::Held->value]);
        }
        if ($quick === 'high_value') {
            $request->merge(['amount_min' => 50000000]); // ₦500,000 in kobo
        }

        return $request;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function filterRowsByQuickView(\Illuminate\Support\Collection $rows, string $quick): \Illuminate\Support\Collection
    {
        $filtered = match ($quick) {
            'due_today' => $rows->filter(fn (array $row) => ($row['due_bucket'] ?? null) === 'today'),
            'due_week' => $rows->filter(fn (array $row) => in_array($row['due_bucket'] ?? null, ['today', 'days_1_3', 'days_4_7'], true)),
            'at_risk' => $rows->filter(fn (array $row) => in_array($row['urgency'] ?? null, ['urgent', 'warning'], true) || ($row['status'] ?? '') === 'disputed'),
            'disputed' => $rows->filter(fn (array $row) => ($row['status'] ?? '') === 'disputed'),
            'pending_review' => $rows->filter(fn (array $row) => ($row['status'] ?? '') === 'held' && ! empty($row['scheduled_release_at'])),
            default => $rows,
        };

        return $filtered->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function recordDetail(FinancialEscrowRecord $record): array
    {
        $detail = $this->audit->escrowRecordDetail($record);
        $record->loadMissing([
            'quest:id,uuid,slug,status,escrow_status,delivery_acknowledged_at,escrow_funded_at,release_hold_until,release_hold_reason,dispute_opened',
            'contract:id,agreed_delivery_date,reference_code,activated_at,generated_at',
            'client:id,name,username,slug,email',
            'freelancer:id,name,username,slug,email',
        ]);

        $quest = $record->quest;
        $releaseAt = $this->scheduledReleaseAt($record);
        $activeDispute = $quest
            ? QuestDispute::query()->where('quest_id', $quest->id)->whereNotIn('status', ['resolved', 'closed_withdrawn'])->first()
            : null;

        $detail['management'] = [
            'status_tone' => $this->statusTone($record, $releaseAt),
            'status_headline' => $this->statusHeadline($record, $releaseAt, $quest),
            'scheduled_release_at' => $releaseAt?->timezone('Africa/Lagos')->toIso8601String(),
            'scheduled_release_label' => $releaseAt?->timezone('Africa/Lagos')->format('D, j M Y · g:i A'),
            'hours_until_release' => $releaseAt ? max(0, (int) now()->diffInHours($releaseAt, false)) : null,
            'on_hold' => $quest?->release_hold_reason !== null || ($quest?->release_hold_until !== null && $quest->release_hold_until->isFuture()),
            'hold_until_label' => $quest?->release_hold_until?->timezone('Africa/Lagos')->format('D, j M Y'),
            'hold_reason' => $quest?->release_hold_reason,
            'frozen' => $quest?->escrow_frozen_at !== null,
            'delivery_submitted_at' => $quest?->delivery_acknowledged_at?->timezone('Africa/Lagos')->toIso8601String(),
            'active_dispute' => $activeDispute ? [
                'uuid' => $activeDispute->uuid,
                'status' => $activeDispute->status->value ?? (string) $activeDispute->status,
                'url' => route('disputes.show', $activeDispute),
            ] : null,
            'quest_url' => $quest ? route('quests.show', $quest->getRouteKey()) : null,
            'contract_url' => $record->contract_reference
                ? route('admin.contracts.view', $record->contract_reference)
                : null,
            'audit_record_url' => route('admin.financial-audit.escrow-records.show', $record),
            'action_routes' => $quest ? [
                'escrow_action' => route('admin.financial.escrows.action', $quest),
                'hold_release' => route('admin.quests.release.hold', $quest),
                'lift_hold' => route('admin.quests.release.lift-hold', $quest),
            ] : [],
            'timeline' => $this->statusTimeline($record, $quest, $releaseAt),
            'vat' => [
                'platform_fee_minor' => (int) $record->platform_fee_minor,
                'platform_fee_display' => NgnMoney::format((int) $record->platform_fee_minor),
                'vat_minor' => (int) $record->vat_minor,
                'vat_display' => NgnMoney::format((int) $record->vat_minor),
                'vat_percent' => (float) $record->vat_percent,
                'freelancer_net_display' => NgnMoney::format((int) $record->freelancer_net_minor),
            ],
            'parties' => [
                'client' => [
                    'name' => $record->client?->name ?? $record->client_name,
                    'username' => $record->client?->username,
                    'email' => $record->client?->email,
                ],
                'freelancer' => [
                    'name' => $record->freelancer?->name ?? $record->freelancer_name,
                    'username' => $record->freelancer?->username,
                    'email' => $record->freelancer?->email,
                ],
            ],
        ];

        return $detail;
    }

    /**
     * @return array<string, mixed>
     */
    public function reconciliationSnapshot(): array
    {
        $report = $this->reconciliation->report(request());
        $position = $this->positionStatement();

        return array_merge($report, [
            'escrow_position' => $position,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function positionStatement(): array
    {
        $now = now()->timezone('Africa/Lagos');
        $heldQuery = FinancialEscrowRecord::query()->where('status', FinancialEscrowRecordStatus::Held->value);
        $totalHeld = (int) $heldQuery->sum('total_funded_minor');
        $heldCount = (int) $heldQuery->count();

        $disputedTotal = (int) FinancialEscrowRecord::query()
            ->where('status', FinancialEscrowRecordStatus::Disputed->value)
            ->sum('total_funded_minor');
        $disputedCount = (int) FinancialEscrowRecord::query()
            ->where('status', FinancialEscrowRecordStatus::Disputed->value)
            ->count();

        $clearingMinor = (int) WalletWithdrawal::query()
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount_minor');
        $clearingCount = (int) WalletWithdrawal::query()
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $dueToday = $this->sumHeldInReleaseBucket('today');
        $dueTodayCount = $this->countHeldInReleaseBucket('today');
        $dueWeek = $this->sumHeldInReleaseBuckets(['today', 'days_1_3', 'days_4_7']);
        $dueWeekCount = $this->countHeldInReleaseBuckets(['today', 'days_1_3', 'days_4_7']);

        $atRiskMinor = $disputedTotal;
        $atRiskCount = $disputedCount;
        $riskRatio = $totalHeld > 0 ? round(($atRiskMinor / $totalHeld) * 100, 1) : 0.0;
        $healthStatus = match (true) {
            $riskRatio > 10 => 'critical',
            $riskRatio > 5 => 'warning',
            default => 'healthy',
        };

        $ledgerLiability = abs($this->ledger->accountBalanceMinor(LedgerAccount::ClientEscrowLiability));

        return [
            'as_of_label' => $now->format('M j, g:i A'),
            'total_held_minor' => $totalHeld,
            'total_held_display' => NgnMoney::format($totalHeld),
            'held_count' => $heldCount,
            'due_today_minor' => $dueToday,
            'due_today_display' => NgnMoney::format($dueToday),
            'due_today_count' => $dueTodayCount,
            'due_week_minor' => $dueWeek,
            'due_week_display' => NgnMoney::format($dueWeek),
            'due_week_count' => $dueWeekCount,
            'at_risk_minor' => $atRiskMinor,
            'at_risk_display' => NgnMoney::format($atRiskMinor),
            'at_risk_count' => $atRiskCount,
            'clearing_minor' => $clearingMinor,
            'clearing_display' => NgnMoney::format($clearingMinor),
            'clearing_count' => $clearingCount,
            'health_status' => $healthStatus,
            'health_label' => match ($healthStatus) {
                'critical' => 'Critical',
                'warning' => 'Monitor',
                default => 'Healthy',
            },
            'at_risk_ratio_percent' => $riskRatio,
            'ledger_liability_display' => NgnMoney::format($ledgerLiability),
            'position_matches_ledger' => $totalHeld === $ledgerLiability,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metricsCards(): array
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $lastWeekStart = $weekStart->copy()->subWeek();
        $lastWeekEnd = $weekStart->copy()->subSecond();

        $inflowToday = $this->sumFundingsBetween($today, $now);
        $inflowWeek = $this->sumFundingsBetween($weekStart, $now);
        $inflowLastWeek = $this->sumFundingsBetween($lastWeekStart, $lastWeekEnd);

        $outflowToday = $this->sumReleasesBetween($today, $now);
        $outflowWeek = $this->sumReleasesBetween($weekStart, $now);
        $outflowLastWeek = $this->sumReleasesBetween($lastWeekStart, $lastWeekEnd);

        $avgHoldDays = $this->averageHoldDays();
        $disputeRate = $this->disputeRatePercent();

        return [
            'inflow' => [
                'today_display' => NgnMoney::format($inflowToday),
                'week_display' => NgnMoney::format($inflowWeek),
                'trend_percent' => $this->percentChange($inflowLastWeek, $inflowWeek),
                'trend_label' => $this->trendLabel($inflowLastWeek, $inflowWeek),
            ],
            'outflow' => [
                'today_display' => NgnMoney::format($outflowToday),
                'week_display' => NgnMoney::format($outflowWeek),
                'trend_percent' => $this->percentChange($outflowLastWeek, $outflowWeek),
                'trend_label' => abs($this->percentChange($outflowLastWeek, $outflowWeek)) <= 15 ? 'Normal pace' : 'Elevated pace',
            ],
            'average_hold' => [
                'days' => $avgHoldDays,
                'benchmark_days' => 5,
                'status' => $avgHoldDays <= 5 ? 'efficient' : ($avgHoldDays <= 8 ? 'good' : 'slow'),
                'status_label' => $avgHoldDays <= 5 ? 'Efficient' : ($avgHoldDays <= 8 ? 'Good' : 'Slow'),
            ],
            'dispute_rate' => [
                'count' => (int) FinancialEscrowRecord::query()->where('status', FinancialEscrowRecordStatus::Disputed->value)->count(),
                'total_display' => NgnMoney::format((int) FinancialEscrowRecord::query()->where('status', FinancialEscrowRecordStatus::Disputed->value)->sum('total_funded_minor')),
                'rate_percent' => $disputeRate,
                'trend_label' => $disputeRate <= 1 ? 'Improving' : 'Elevated',
            ],
        ];
    }

    /**
     * @return list<array{date: string, label: string, balance_minor: int, balance_display: string}>
     */
    private function balanceSeries30d(): array
    {
        $series = [];
        $cursor = now()->subDays(29)->startOfDay();

        for ($i = 0; $i < 30; $i++) {
            $dayEnd = $cursor->copy()->endOfDay();
            $balance = $this->heldBalanceAsOf($dayEnd);
            $series[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('M j'),
                'balance_minor' => $balance,
                'balance_display' => NgnMoney::format($balance),
            ];
            $cursor->addDay();
        }

        $peak = collect($series)->sortByDesc('balance_minor')->first();

        return [
            'points' => $series,
            'peak_annotation' => $peak
                ? 'Escrow peaked '.$peak['label'].' at '.$peak['balance_display']
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseWaterfall(): array
    {
        $buckets = [
            'today' => ['label' => 'Due today', 'minor' => 0, 'count' => 0],
            'days_1_3' => ['label' => 'Due 1–3 days', 'minor' => 0, 'count' => 0],
            'days_4_7' => ['label' => 'Due 4–7 days', 'minor' => 0, 'count' => 0],
            'days_8_plus' => ['label' => 'Due 8+ days', 'minor' => 0, 'count' => 0],
        ];

        FinancialEscrowRecord::query()
            ->with(['contract:id,agreed_delivery_date', 'quest:id,delivery_acknowledged_at'])
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->orderBy('funded_at')
            ->chunk(200, function ($records) use (&$buckets): void {
                foreach ($records as $record) {
                    $bucket = $this->releaseBucketKey($record);
                    $buckets[$bucket]['minor'] += (int) $record->total_funded_minor;
                    $buckets[$bucket]['count']++;
                }
            });

        $segments = collect($buckets)->map(fn (array $bucket, string $key) => [
            'key' => $key,
            'label' => $bucket['label'],
            'amount_minor' => $bucket['minor'],
            'amount_display' => NgnMoney::format($bucket['minor']),
            'count' => $bucket['count'],
        ])->values()->all();

        return ['segments' => $segments];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthGauges(): array
    {
        $position = $this->positionStatement();
        $totalHeld = max(1, (int) $position['total_held_minor']);
        $ledgerLiability = abs($this->ledger->accountBalanceMinor(LedgerAccount::ClientEscrowLiability));
        $solvency = min(100, (int) round(($ledgerLiability / $totalHeld) * 100));

        $allCount = max(1, (int) FinancialEscrowRecord::query()->count());
        $disputedCount = (int) FinancialEscrowRecord::query()->where('status', FinancialEscrowRecordStatus::Disputed->value)->count();
        $disputeRate = round(($disputedCount / $allCount) * 100, 1);

        $releasedOnTime = $this->releaseTimelinessPercent();
        $autoReleaseCapacity = $this->autoReleaseCapacityPercent();

        $scores = [
            ['key' => 'solvency', 'label' => 'Solvency ratio', 'percent' => $solvency, 'ideal' => '>90%', 'status' => $this->gaugeStatus($solvency, 90)],
            ['key' => 'dispute_rate', 'label' => 'Dispute rate', 'percent' => max(0, 100 - (int) round($disputeRate * 10)), 'ideal' => '<1%', 'status' => $disputeRate <= 1 ? 'excellent' : ($disputeRate <= 3 ? 'good' : 'monitor')],
            ['key' => 'release_timeliness', 'label' => 'Release timeliness', 'percent' => $releasedOnTime, 'ideal' => '>95%', 'status' => $this->gaugeStatus($releasedOnTime, 95)],
            ['key' => 'auto_release_capacity', 'label' => 'Auto-release capacity', 'percent' => $autoReleaseCapacity, 'ideal' => '>85%', 'status' => $this->gaugeStatus($autoReleaseCapacity, 85)],
        ];

        $overall = collect($scores)->every(fn ($s) => in_array($s['status'], ['excellent', 'good'], true))
            ? 'healthy'
            : (collect($scores)->contains(fn ($s) => $s['status'] === 'critical') ? 'critical' : 'monitor');

        return [
            'gauges' => $scores,
            'overall_status' => $overall,
            'overall_label' => match ($overall) {
                'critical' => 'Critical',
                'monitor' => 'Monitor',
                default => 'Healthy',
            },
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alerts(): array
    {
        $alerts = [];

        $dueTodayCount = $this->countHeldInReleaseBucket('today');
        if ($dueTodayCount > 0) {
            $alerts[] = [
                'severity' => 'amber',
                'message' => "{$dueTodayCount} escrow".($dueTodayCount === 1 ? '' : 's').' due for release in 24 hours',
                'filter' => 'due_today',
            ];
        }

        $disputedCount = (int) FinancialEscrowRecord::query()->where('status', FinancialEscrowRecordStatus::Disputed->value)->count();
        if ($disputedCount > 0) {
            $alerts[] = [
                'severity' => 'red',
                'message' => "{$disputedCount} disputed escrow".($disputedCount === 1 ? '' : 's').' requiring Super Admin attention',
                'filter' => 'disputed',
            ];
        }

        $openExceptions = (int) FinancialReconciliationException::query()
            ->whereIn('status', ['open', 'under_investigation'])
            ->count();
        if ($openExceptions > 0) {
            $alerts[] = [
                'severity' => 'red',
                'message' => "{$openExceptions} reconciliation exception".($openExceptions === 1 ? '' : 's').' open',
                'href' => route('admin.financial-audit.exceptions.index'),
            ];
        }

        if (! $this->ledger->globalBalanceCheck()['balanced']) {
            $alerts[] = [
                'severity' => 'red',
                'message' => 'Ledger debits and credits do not balance',
                'href' => route('admin.financial-audit.index'),
            ];
        }

        return $alerts;
    }

    private function enrichTableRow(array $row): array
    {
        $record = FinancialEscrowRecord::query()
            ->with(['contract:id,agreed_delivery_date', 'quest:id,delivery_acknowledged_at,release_hold_until'])
            ->find($row['id'] ?? 0);

        if ($record === null) {
            return $row + ['urgency' => 'neutral', 'due_bucket' => null];
        }

        $releaseAt = $this->scheduledReleaseAt($record);
        $urgency = $this->statusTone($record, $releaseAt);

        return array_merge($row, [
            'urgency' => $urgency,
            'due_bucket' => $this->releaseBucketKey($record),
            'scheduled_release_label' => $releaseAt?->timezone('Africa/Lagos')->format('M j'),
            'scheduled_release_at' => $releaseAt?->toIso8601String(),
            'show_url' => route('admin.escrow-management.records.show', $record),
        ]);
    }

    private function scheduledReleaseAt(FinancialEscrowRecord $record): ?Carbon
    {
        $due = $record->contract?->agreed_delivery_date;
        if ($due === null) {
            return null;
        }

        $dueCarbon = Carbon::parse($due)->endOfDay();

        if ($record->quest?->delivery_acknowledged_at !== null) {
            return EscrowAutoReleasePolicy::releaseAt($dueCarbon);
        }

        return $dueCarbon;
    }

    private function releaseBucketKey(FinancialEscrowRecord $record): string
    {
        $releaseAt = $this->scheduledReleaseAt($record);
        if ($releaseAt === null) {
            return 'days_8_plus';
        }

        $days = (int) now()->startOfDay()->diffInDays($releaseAt->copy()->startOfDay(), false);

        return match (true) {
            $days <= 0 => 'today',
            $days <= 3 => 'days_1_3',
            $days <= 7 => 'days_4_7',
            default => 'days_8_plus',
        };
    }

    /**
     * @param  list<string>  $buckets
     */
    private function sumHeldInReleaseBuckets(array $buckets): int
    {
        $sum = 0;
        FinancialEscrowRecord::query()
            ->with(['contract:id,agreed_delivery_date', 'quest:id,delivery_acknowledged_at'])
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->chunk(200, function ($records) use (&$sum, $buckets): void {
                foreach ($records as $record) {
                    if (in_array($this->releaseBucketKey($record), $buckets, true)) {
                        $sum += (int) $record->total_funded_minor;
                    }
                }
            });

        return $sum;
    }

    private function sumHeldInReleaseBucket(string $bucket): int
    {
        return $this->sumHeldInReleaseBuckets([$bucket]);
    }

    /**
     * @param  list<string>  $buckets
     */
    private function countHeldInReleaseBuckets(array $buckets): int
    {
        $count = 0;
        FinancialEscrowRecord::query()
            ->with(['contract:id,agreed_delivery_date', 'quest:id,delivery_acknowledged_at'])
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->chunk(200, function ($records) use (&$count, $buckets): void {
                foreach ($records as $record) {
                    if (in_array($this->releaseBucketKey($record), $buckets, true)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function countHeldInReleaseBucket(string $bucket): int
    {
        return $this->countHeldInReleaseBuckets([$bucket]);
    }

    private function heldBalanceAsOf(Carbon $asOf): int
    {
        $funded = (int) FinancialEscrowRecord::query()
            ->where('funded_at', '<=', $asOf)
            ->sum('total_funded_minor');
        $released = (int) FinancialEscrowRecord::query()
            ->whereNotNull('released_at')
            ->where('released_at', '<=', $asOf)
            ->sum('total_funded_minor');
        $refunded = (int) FinancialEscrowRecord::query()
            ->whereNotNull('refunded_at')
            ->where('refunded_at', '<=', $asOf)
            ->sum('total_funded_minor');

        return max(0, $funded - $released - $refunded);
    }

    private function sumFundingsBetween(Carbon $from, Carbon $to): int
    {
        return (int) FinancialEscrowRecord::query()
            ->whereBetween('funded_at', [$from, $to])
            ->sum('total_funded_minor');
    }

    private function sumReleasesBetween(Carbon $from, Carbon $to): int
    {
        return (int) FinancialEscrowRecord::query()
            ->whereBetween('released_at', [$from, $to])
            ->sum('total_funded_minor');
    }

    private function averageHoldDays(): float
    {
        $held = FinancialEscrowRecord::query()
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->whereNotNull('funded_at')
            ->get(['funded_at']);

        if ($held->isEmpty()) {
            $released = FinancialEscrowRecord::query()
                ->whereNotNull('funded_at')
                ->whereNotNull('released_at')
                ->orderByDesc('released_at')
                ->limit(100)
                ->get(['funded_at', 'released_at']);

            if ($released->isEmpty()) {
                return 0;
            }

            return round($released->avg(fn ($r) => $r->funded_at->diffInDays($r->released_at)), 1);
        }

        return round($held->avg(fn ($r) => $r->funded_at->diffInDays(now())), 1);
    }

    private function disputeRatePercent(): float
    {
        $total = (int) FinancialEscrowRecord::query()->count();
        if ($total === 0) {
            return 0.0;
        }

        $disputed = (int) FinancialEscrowRecord::query()->where('status', FinancialEscrowRecordStatus::Disputed->value)->count();

        return round(($disputed / $total) * 100, 1);
    }

    private function releaseTimelinessPercent(): int
    {
        $released = FinancialEscrowRecord::query()
            ->whereNotNull('released_at')
            ->orderByDesc('released_at')
            ->limit(200)
            ->with(['contract:id,agreed_delivery_date', 'quest:id,delivery_acknowledged_at'])
            ->get();

        if ($released->isEmpty()) {
            return 95;
        }

        $onTime = $released->filter(function (FinancialEscrowRecord $record) {
            $scheduled = $this->scheduledReleaseAt($record);

            return $scheduled === null || $record->released_at->lte($scheduled->copy()->addDay());
        })->count();

        return (int) round(($onTime / $released->count()) * 100);
    }

    private function autoReleaseCapacityPercent(): int
    {
        $held = (int) FinancialEscrowRecord::query()->where('status', FinancialEscrowRecordStatus::Held->value)->count();
        if ($held === 0) {
            return 100;
        }

        $withSchedule = 0;
        FinancialEscrowRecord::query()
            ->with(['contract:id,agreed_delivery_date', 'quest:id,delivery_acknowledged_at'])
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->chunk(200, function ($records) use (&$withSchedule): void {
                foreach ($records as $record) {
                    if ($this->scheduledReleaseAt($record) !== null) {
                        $withSchedule++;
                    }
                }
            });

        return (int) round(($withSchedule / $held) * 100);
    }

    private function gaugeStatus(int $percent, int $threshold): string
    {
        return match (true) {
            $percent >= $threshold => 'excellent',
            $percent >= ($threshold - 15) => 'good',
            $percent >= ($threshold - 30) => 'monitor',
            default => 'critical',
        };
    }

    private function percentChange(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function trendLabel(int $previous, int $current): string
    {
        $change = $this->percentChange($previous, $current);
        if ($change > 0) {
            return '↑ '.$change.'% vs last week';
        }
        if ($change < 0) {
            return '↓ '.abs($change).'% vs last week';
        }

        return 'Flat vs last week';
    }

    private function statusTone(FinancialEscrowRecord $record, ?Carbon $releaseAt): string
    {
        if (in_array($record->status, [FinancialEscrowRecordStatus::Disputed->value, 'disputed'], true)) {
            return 'urgent';
        }

        if (in_array($record->status, [FinancialEscrowRecordStatus::Released->value, 'released', 'refunded'], true)) {
            return 'released';
        }

        if ($releaseAt !== null && $releaseAt->isPast()) {
            return 'urgent';
        }

        if ($releaseAt !== null && $releaseAt->lte(now()->addDay())) {
            return 'warning';
        }

        return 'healthy';
    }

    private function statusHeadline(FinancialEscrowRecord $record, ?Carbon $releaseAt, ?Quest $quest): string
    {
        if ($record->status === FinancialEscrowRecordStatus::Disputed->value) {
            return 'Disputed — escrow frozen pending ruling';
        }

        if ($record->status === FinancialEscrowRecordStatus::Released->value) {
            return 'Released — payout processed';
        }

        if ($quest?->release_hold_until?->isFuture()) {
            return 'On hold until '.$quest->release_hold_until->timezone('Africa/Lagos')->format('M j');
        }

        if ($quest?->delivery_acknowledged_at) {
            return $releaseAt
                ? 'Awaiting client approval — auto-release '.$releaseAt->timezone('Africa/Lagos')->format('M j, g:i A')
                : 'Awaiting client approval';
        }

        return 'Held — work in progress';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statusTimeline(FinancialEscrowRecord $record, ?Quest $quest, ?Carbon $releaseAt): array
    {
        $timeline = [];

        if ($record->funded_at) {
            $timeline[] = [
                'key' => 'funded',
                'label' => 'Escrow funded',
                'at' => $record->funded_at->timezone('Africa/Lagos')->toIso8601String(),
                'at_label' => $record->funded_at->timezone('Africa/Lagos')->format('M j, g:i A'),
                'detail' => NgnMoney::format((int) $record->total_funded_minor).' received · '.$record->gateway_name,
                'completed' => true,
                'current' => false,
            ];
        }

        if ($quest?->delivery_acknowledged_at) {
            $timeline[] = [
                'key' => 'delivery',
                'label' => 'Work submitted',
                'at' => $quest->delivery_acknowledged_at->timezone('Africa/Lagos')->toIso8601String(),
                'at_label' => $quest->delivery_acknowledged_at->timezone('Africa/Lagos')->format('M j, g:i A'),
                'detail' => 'Client review window active',
                'completed' => true,
                'current' => $record->status === FinancialEscrowRecordStatus::Held->value,
            ];
        }

        if ($releaseAt && $record->status === FinancialEscrowRecordStatus::Held->value) {
            $timeline[] = [
                'key' => 'scheduled_release',
                'label' => 'Scheduled release',
                'at' => $releaseAt->timezone('Africa/Lagos')->toIso8601String(),
                'at_label' => $releaseAt->timezone('Africa/Lagos')->format('M j, g:i A'),
                'detail' => 'Automatic release if no dispute filed',
                'completed' => false,
                'current' => true,
            ];
        }

        if ($record->released_at) {
            $timeline[] = [
                'key' => 'released',
                'label' => 'Escrow released',
                'at' => $record->released_at->timezone('Africa/Lagos')->toIso8601String(),
                'at_label' => $record->released_at->timezone('Africa/Lagos')->format('M j, g:i A'),
                'detail' => NgnMoney::format((int) $record->freelancer_net_minor).' to freelancer',
                'completed' => true,
                'current' => false,
            ];
        }

        return $timeline;
    }
}
