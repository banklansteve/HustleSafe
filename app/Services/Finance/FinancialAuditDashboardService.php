<?php

namespace App\Services\Finance;

use App\Enums\FinancialEscrowRecordStatus;
use App\Enums\LedgerAccount;
use App\Enums\LedgerEventType;
use App\Enums\ReconciliationExceptionStatus;
use App\Models\FinancialEscrowRecord;
use App\Models\FinancialReconciliationException;
use App\Models\FinancialReconciliationRun;
use App\Models\LedgerEntry;
use App\Models\LedgerJournalBatch;
use App\Models\QuestCategory;
use App\Models\VatRemittance;
use App\Models\Wallet;
use App\Models\WalletWithdrawal;
use App\Support\NgnMoney;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class FinancialAuditDashboardService
{
    public function __construct(
        private readonly DoubleEntryLedgerService $ledger,
    ) {}

    /**
     * Full overview payload for the Financial Audit dashboard.
     *
     * @return array<string, mixed>
     */
    public function overview(Request $request): array
    {
        [$from, $to] = $this->resolvePeriod($request);
        $now = now();

        $lastRun = FinancialReconciliationRun::query()->latest('id')->first();
        $openExceptions = FinancialReconciliationException::query()
            ->whereIn('status', [
                ReconciliationExceptionStatus::Open->value,
                ReconciliationExceptionStatus::UnderInvestigation->value,
            ])
            ->count();

        $today = $now->copy()->startOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $yearStart = $now->copy()->startOfYear();
        $weekStart = $now->copy()->startOfWeek();

        $feeRevenueMonth = $this->feeRevenueBetween($monthStart, $now);
        $feeRevenuePrevMonth = $this->feeRevenueBetween(
            $monthStart->copy()->subMonth(),
            $monthStart->copy()->subSecond(),
        );
        $feeRevenueYear = $this->feeRevenueSince($yearStart);
        $feeRevenuePrevYear = $this->feeRevenueBetween(
            $yearStart->copy()->subYear(),
            $yearStart->copy()->subSecond(),
        );

        $currentQuarterStart = $this->quarterStart($now);
        $previousQuarterStart = $currentQuarterStart->copy()->subQuarter();
        $previousQuarterEnd = $currentQuarterStart->copy()->subSecond();

        return [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'period' => $this->periodSummary($from, $to),
            'escrow_position' => $this->escrowPosition(),
            'revenue' => [
                'today_minor' => $this->feeRevenueBetween($today, $now),
                'today_display' => NgnMoney::format($this->feeRevenueBetween($today, $now)),
                'week_minor' => $this->feeRevenueBetween($weekStart, $now),
                'week_display' => NgnMoney::format($this->feeRevenueBetween($weekStart, $now)),
                'month_minor' => $feeRevenueMonth,
                'month_display' => NgnMoney::format($feeRevenueMonth),
                'year_minor' => $feeRevenueYear,
                'year_display' => NgnMoney::format($feeRevenueYear),
                'mom_change_percent' => $this->percentChange($feeRevenuePrevMonth, $feeRevenueMonth),
                'yoy_change_percent' => $this->percentChange($feeRevenuePrevYear, $feeRevenueYear),
            ],
            'vat' => [
                'today_minor' => $this->vatAccruedBetween($today, $now),
                'today_display' => NgnMoney::format($this->vatAccruedBetween($today, $now)),
                'month_minor' => $this->vatAccruedBetween($monthStart, $now),
                'month_display' => NgnMoney::format($this->vatAccruedBetween($monthStart, $now)),
                'quarter_minor' => $this->vatAccruedBetween($currentQuarterStart, $now),
                'quarter_display' => NgnMoney::format($this->vatAccruedBetween($currentQuarterStart, $now)),
                'current_quarter_label' => $this->quarterLabel($now),
                'current_quarter_payable_minor' => $this->vatPayableForQuarter($currentQuarterStart, $now),
                'current_quarter_payable_display' => NgnMoney::format($this->vatPayableForQuarter($currentQuarterStart, $now)),
                'previous_quarter_label' => $this->quarterLabel($previousQuarterStart),
                'previous_quarter_payable_minor' => $this->vatPayableForQuarter($previousQuarterStart, $previousQuarterEnd),
                'previous_quarter_payable_display' => NgnMoney::format($this->vatPayableForQuarter($previousQuarterStart, $previousQuarterEnd)),
            ],
            'freelancer_wallets' => $this->freelancerWalletSnapshot($today, $monthStart, $now),
            'reconciliation' => [
                'last_run_at' => $lastRun?->finished_at?->toIso8601String(),
                'last_run_status' => $lastRun?->status,
                'open_exceptions' => $openExceptions,
                'passed' => $lastRun?->status === 'passed',
            ],
            'ledger_accounts' => $this->ledgerAccountBalances(),
            'ledger_balanced' => $this->ledger->globalBalanceCheck()['balanced'],
            'ledger_balance_check' => $this->ledger->globalBalanceCheck(),
            'active_escrows' => $this->activeEscrows(),
            'period_fundings' => $this->escrowsFundedInPeriod($from, $to, 50),
            'cash_flow' => $this->cashFlowSeriesForRange($from, $to),
            'recent_reconciliation_runs' => $this->recentReconciliationRuns(),
        ];
    }

    /** @deprecated Use overview() */
    public function snapshot(?Request $request = null): array
    {
        return $this->overview($request ?? request());
    }

    /**
     * @return array<string, mixed>
     */
    public function escrowLedgerListing(Request $request): array
    {
        $query = $this->escrowQuery($request);
        $paginator = $query->paginate(25)->withQueryString();
        $rows = collect($paginator->items())->map(fn (FinancialEscrowRecord $r) => $this->escrowRow($r, includeSchedule: true));

        $totalsQuery = $this->escrowQuery($request)->reorder();

        return [
            'data' => $rows->all(),
            'totals' => $this->totalsForQuery($totalsQuery),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => $request->only(['status', 'from', 'to', 'client_id', 'freelancer_id', 'category_id', 'amount_min', 'amount_max', 'q']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function escrowRecordDetail(FinancialEscrowRecord $record): array
    {
        $record->load(['client:id,name,email', 'freelancer:id,name,email', 'category:id,name', 'quest:id,uuid', 'contract:id,agreed_delivery_date,reference_code']);

        $batches = LedgerJournalBatch::query()
            ->where('payment_escrow_id', $record->payment_escrow_id)
            ->with('entries')
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (LedgerJournalBatch $batch) => [
                'reference' => $batch->reference,
                'event_type' => $batch->event_type,
                'event_label' => str_replace('_', ' ', $batch->event_type),
                'description' => $batch->description,
                'occurred_at' => $batch->occurred_at?->toIso8601String(),
                'created_by_process' => $batch->created_by_process,
                'reversal_reason' => $batch->reversal_reason,
                'entries' => $batch->entries->map(fn ($e) => [
                    'uuid' => $e->uuid,
                    'account' => $e->accountLabel(),
                    'account_key' => $e->ledger_account,
                    'side' => $e->side,
                    'amount_minor' => (int) $e->amount_minor,
                    'amount_display' => NgnMoney::format((int) $e->amount_minor),
                ])->all(),
            ])->all();

        $debits = collect($batches)->flatMap(fn ($b) => $b['entries'])->where('side', 'debit')->sum('amount_minor');
        $credits = collect($batches)->flatMap(fn ($b) => $b['entries'])->where('side', 'credit')->sum('amount_minor');

        return [
            'record' => $this->escrowRow($record, detailed: true),
            'ledger_trail' => $batches,
            'ledger_trail_balanced' => $debits === $credits,
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolvePeriod(Request $request): array
    {
        $preset = (string) $request->query('preset', '');
        $now = now();

        if ($preset !== '') {
            return match ($preset) {
                'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
                '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
                'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
                'quarter' => [$this->quarterStart($now), $now->copy()->endOfDay()],
                'year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
                default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            };
        }

        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : $now->copy()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : $now->copy()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodSummary(Carbon $from, Carbon $to): array
    {
        $escrowFunded = $this->ledgerSumInPeriod(
            LedgerAccount::ClientEscrowLiability,
            'credit',
            LedgerEventType::EscrowFunded,
            $from,
            $to,
        );
        $platformFees = $this->feeRevenueBetween($from, $to);
        $vatAccrued = $this->vatAccruedBetween($from, $to);
        $releasedToFreelancers = $this->ledgerSumInPeriod(
            LedgerAccount::FreelancerPayable,
            'credit',
            LedgerEventType::EscrowReleased,
            $from,
            $to,
        );
        $refunded = $this->ledgerSumInPeriod(
            LedgerAccount::RefundPayable,
            'credit',
            LedgerEventType::DisputeRefund,
            $from,
            $to,
        );
        $withdrawals = $this->ledgerSumInPeriod(
            LedgerAccount::WithdrawalClearing,
            'credit',
            LedgerEventType::WithdrawalConfirmed,
            $from,
            $to,
        );

        $fundingCount = (int) FinancialEscrowRecord::query()
            ->whereBetween('funded_at', [$from, $to])
            ->count();

        $releasedCount = (int) FinancialEscrowRecord::query()
            ->whereBetween('released_at', [$from, $to])
            ->count();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'label' => $from->format('d M Y').' – '.$to->format('d M Y'),
            'escrow_funded_minor' => $escrowFunded,
            'escrow_funded_display' => NgnMoney::format($escrowFunded),
            'escrow_funding_count' => $fundingCount,
            'platform_fee_minor' => $platformFees,
            'platform_fee_display' => NgnMoney::format($platformFees),
            'vat_minor' => $vatAccrued,
            'vat_display' => NgnMoney::format($vatAccrued),
            'released_to_freelancers_minor' => $releasedToFreelancers,
            'released_to_freelancers_display' => NgnMoney::format($releasedToFreelancers),
            'released_count' => $releasedCount,
            'refunded_minor' => $refunded,
            'refunded_display' => NgnMoney::format($refunded),
            'withdrawals_minor' => $withdrawals,
            'withdrawals_display' => NgnMoney::format($withdrawals),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function escrowPosition(): array
    {
        $now = now();
        $heldQuery = FinancialEscrowRecord::query()->where('status', FinancialEscrowRecordStatus::Held->value);
        $heldTotal = (int) $heldQuery->sum('total_funded_minor');
        $heldCount = (int) $heldQuery->count();
        $oldestHeld = FinancialEscrowRecord::query()
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->whereNotNull('funded_at')
            ->orderBy('funded_at')
            ->value('funded_at');
        $disputedTotal = (int) FinancialEscrowRecord::query()
            ->where('status', FinancialEscrowRecordStatus::Disputed->value)
            ->sum('total_funded_minor');
        $disputedCount = (int) FinancialEscrowRecord::query()
            ->where('status', FinancialEscrowRecordStatus::Disputed->value)
            ->count();

        $ledgerLiability = abs($this->ledger->accountBalanceMinor(LedgerAccount::ClientEscrowLiability));
        $variance = $heldTotal - $ledgerLiability;

        return [
            'total_held_minor' => $heldTotal,
            'total_held_display' => NgnMoney::format($heldTotal),
            'active_count' => $heldCount,
            'oldest_active_days' => $oldestHeld ? (int) Carbon::parse($oldestHeld)->diffInDays($now) : 0,
            'disputed_total_minor' => $disputedTotal,
            'disputed_total_display' => NgnMoney::format($disputedTotal),
            'disputed_count' => $disputedCount,
            'ledger_liability_minor' => $ledgerLiability,
            'ledger_liability_display' => NgnMoney::format($ledgerLiability),
            'variance_minor' => $variance,
            'variance_display' => NgnMoney::format(abs($variance)),
            'position_matches_ledger' => $variance === 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ledgerAccountBalances(): array
    {
        return collect(LedgerAccount::cases())->map(function (LedgerAccount $account) {
            $balance = $this->ledger->accountBalanceMinor($account);

            return [
                'key' => $account->value,
                'label' => $account->label(),
                'balance_minor' => $balance,
                'balance_display' => NgnMoney::format(abs($balance)),
                'is_liability' => in_array($account, LedgerAccount::normalBalanceSide(), true),
                'warning' => $account === LedgerAccount::PaymentGatewaySuspense && $balance !== 0,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeEscrows(): array
    {
        return FinancialEscrowRecord::query()
            ->with(['contract:id,agreed_delivery_date,reference_code', 'category:id,name'])
            ->whereIn('status', [
                FinancialEscrowRecordStatus::Held->value,
                FinancialEscrowRecordStatus::Disputed->value,
            ])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [FinancialEscrowRecordStatus::Disputed->value])
            ->orderBy('funded_at')
            ->limit(100)
            ->get()
            ->map(fn (FinancialEscrowRecord $r) => $this->escrowRow($r, includeSchedule: true))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function escrowsFundedInPeriod(Carbon $from, Carbon $to, int $limit = 50): array
    {
        return FinancialEscrowRecord::query()
            ->with(['contract:id,agreed_delivery_date', 'category:id,name'])
            ->whereBetween('funded_at', [$from, $to])
            ->orderByDesc('funded_at')
            ->limit($limit)
            ->get()
            ->map(fn (FinancialEscrowRecord $r) => $this->escrowRow($r, includeSchedule: true))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentReconciliationRuns(): array
    {
        return FinancialReconciliationRun::query()
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($run) => [
                'id' => $run->id,
                'uuid' => $run->uuid,
                'status' => $run->status,
                'passed' => $run->status === 'passed',
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'duration_seconds' => $run->started_at && $run->finished_at
                    ? max(0, (int) $run->started_at->diffInSeconds($run->finished_at))
                    : null,
                'records_processed' => (int) $run->records_processed,
                'exceptions_found' => (int) $run->exceptions_found,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function freelancerWalletSnapshot(Carbon $today, Carbon $monthStart, Carbon $now): array
    {
        $walletTotal = (int) Wallet::query()->sum('balance_minor');
        $pendingWithdrawals = WalletWithdrawal::query()->whereIn('status', ['pending', 'processing'])->count();
        $pendingWithdrawalValue = (int) WalletWithdrawal::query()
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount_minor');

        return [
            'total_balance_minor' => $walletTotal,
            'total_balance_display' => NgnMoney::format($walletTotal),
            'pending_withdrawal_count' => $pendingWithdrawals,
            'pending_withdrawal_minor' => $pendingWithdrawalValue,
            'pending_withdrawal_display' => NgnMoney::format($pendingWithdrawalValue),
            'withdrawn_today_minor' => (int) WalletWithdrawal::query()
                ->where('status', 'completed')
                ->where('processed_at', '>=', $today)
                ->sum('amount_minor'),
            'withdrawn_today_display' => NgnMoney::format((int) WalletWithdrawal::query()
                ->where('status', 'completed')
                ->where('processed_at', '>=', $today)
                ->sum('amount_minor')),
            'withdrawn_month_minor' => (int) WalletWithdrawal::query()
                ->where('status', 'completed')
                ->where('processed_at', '>=', $monthStart)
                ->sum('amount_minor'),
            'withdrawn_month_display' => NgnMoney::format((int) WalletWithdrawal::query()
                ->where('status', 'completed')
                ->where('processed_at', '>=', $monthStart)
                ->sum('amount_minor')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function escrowLedgerExportRows(Request $request): array
    {
        return $this->escrowQuery($request)
            ->limit(5000)
            ->get()
            ->map(fn (FinancialEscrowRecord $r) => $this->escrowRow($r))
            ->all();
    }

    private function escrowQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = FinancialEscrowRecord::query()
            ->with(['client:id,name', 'freelancer:id,name', 'category:id,name', 'contract:id,agreed_delivery_date,reference_code'])
            ->orderByDesc('funded_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('funded_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('funded_at', '<=', $to);
        }

        if ($clientId = $request->query('client_id')) {
            $query->where('client_id', (int) $clientId);
        }

        if ($freelancerId = $request->query('freelancer_id')) {
            $query->where('freelancer_id', (int) $freelancerId);
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('quest_category_id', (int) $categoryId);
        }

        if ($min = $request->query('amount_min')) {
            $query->where('total_funded_minor', '>=', (int) $min);
        }

        if ($max = $request->query('amount_max')) {
            $query->where('total_funded_minor', '<=', (int) $max);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('escrow_reference', 'like', "%{$search}%")
                    ->orWhere('contract_reference', 'like', "%{$search}%")
                    ->orWhere('quest_title', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('freelancer_name', 'like', "%{$search}%")
                    ->orWhere('paystack_reference', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function totalsForQuery(\Illuminate\Database\Eloquent\Builder $query): array
    {
        $totals = $query->selectRaw('
            count(*) as row_count,
            coalesce(sum(total_funded_minor), 0) as gross_minor,
            coalesce(sum(platform_fee_minor), 0) as fee_minor,
            coalesce(sum(vat_minor), 0) as vat_minor,
            coalesce(sum(freelancer_net_minor), 0) as net_minor
        ')->first();

        return [
            'count' => (int) ($totals->row_count ?? 0),
            'gross_display' => NgnMoney::format((int) ($totals->gross_minor ?? 0)),
            'fee_display' => NgnMoney::format((int) ($totals->fee_minor ?? 0)),
            'vat_display' => NgnMoney::format((int) ($totals->vat_minor ?? 0)),
            'net_display' => NgnMoney::format((int) ($totals->net_minor ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function escrowRow(FinancialEscrowRecord $r, bool $detailed = false, bool $includeSchedule = false): array
    {
        $dueDate = $r->contract?->agreed_delivery_date;
        $daysUntilDue = $dueDate ? now()->startOfDay()->diffInDays($dueDate, false) : null;

        $row = [
            'id' => $r->id,
            'uuid' => $r->uuid,
            'quest_id' => $r->quest_id,
            'escrow_reference' => $r->escrow_reference,
            'contract_reference' => $r->contract_reference,
            'quest_title' => $r->quest_title,
            'category' => $r->category?->name,
            'client_id' => $r->client_id,
            'client_name' => $r->client_name,
            'freelancer_id' => $r->freelancer_id,
            'freelancer_name' => $r->freelancer_name,
            'gross_display' => NgnMoney::format((int) $r->gross_contract_value_minor),
            'gross_minor' => (int) $r->gross_contract_value_minor,
            'funded_display' => NgnMoney::format((int) $r->total_funded_minor),
            'funded_minor' => (int) $r->total_funded_minor,
            'platform_fee_display' => NgnMoney::format((int) $r->platform_fee_minor),
            'platform_fee_minor' => (int) $r->platform_fee_minor,
            'platform_fee_percent' => (float) $r->platform_fee_percent,
            'vat_display' => NgnMoney::format((int) $r->vat_minor),
            'vat_minor' => (int) $r->vat_minor,
            'vat_percent' => (float) $r->vat_percent,
            'freelancer_net_display' => NgnMoney::format((int) $r->freelancer_net_minor),
            'freelancer_net_minor' => (int) $r->freelancer_net_minor,
            'status' => $r->status,
            'status_label' => $r->statusEnum()->label(),
            'funded_at' => $r->funded_at?->toIso8601String(),
            'released_at' => $r->released_at?->toIso8601String(),
            'paystack_reference' => $r->paystack_reference,
            'gateway_name' => $r->gateway_name,
        ];

        if ($includeSchedule || $detailed) {
            $row += [
                'due_date' => $dueDate?->toDateString(),
                'due_date_label' => $dueDate?->format('d M Y'),
                'days_until_due' => $daysUntilDue,
                'is_overdue' => $daysUntilDue !== null && $daysUntilDue < 0 && in_array($r->status, ['held', 'disputed'], true),
            ];
        }

        if ($detailed) {
            $row += [
                'release_trigger_type' => $r->release_trigger_type,
                'release_trigger_label' => $r->release_trigger_type ? str_replace('_', ' ', $r->release_trigger_type) : null,
                'wallet_credit_reference' => $r->wallet_credit_reference,
                'fee_recognised_at' => $r->fee_recognised_at?->toIso8601String(),
                'refunded_at' => $r->refunded_at?->toIso8601String(),
                'client_email' => $r->client?->email,
                'freelancer_email' => $r->freelancer?->email,
            ];
        }

        return $row;
    }

    /**
     * @return list<array{date: string, label: string, inflow_minor: int, outflow_minor: int, fee_minor: int, vat_minor: int}>
     */
    private function cashFlowSeriesForRange(Carbon $from, Carbon $to): array
    {
        $days = min(90, max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1));
        $series = [];
        $cursor = $from->copy()->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            if ($cursor->gt($to)) {
                break;
            }
            $dayEnd = $cursor->copy()->endOfDay();
            if ($dayEnd->gt($to)) {
                $dayEnd = $to->copy();
            }

            $series[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('d M'),
                'inflow_minor' => $this->ledgerSumInPeriod(
                    LedgerAccount::ClientEscrowLiability,
                    'credit',
                    LedgerEventType::EscrowFunded,
                    $cursor,
                    $dayEnd,
                ),
                'outflow_minor' => $this->ledgerSumInPeriod(
                    LedgerAccount::WithdrawalClearing,
                    'credit',
                    LedgerEventType::WithdrawalConfirmed,
                    $cursor,
                    $dayEnd,
                ) + $this->ledgerSumInPeriod(
                    LedgerAccount::RefundPayable,
                    'credit',
                    LedgerEventType::DisputeRefund,
                    $cursor,
                    $dayEnd,
                ),
                'fee_minor' => (int) LedgerEntry::query()
                    ->where('ledger_account', LedgerAccount::PlatformFeeRevenue->value)
                    ->where('side', 'credit')
                    ->whereBetween('occurred_at', [$cursor, $dayEnd])
                    ->sum('amount_minor'),
                'vat_minor' => (int) LedgerEntry::query()
                    ->where('ledger_account', LedgerAccount::VatPayable->value)
                    ->where('side', 'credit')
                    ->whereBetween('occurred_at', [$cursor, $dayEnd])
                    ->sum('amount_minor'),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    private function ledgerSumInPeriod(
        LedgerAccount $account,
        string $side,
        LedgerEventType $eventType,
        Carbon $from,
        Carbon $to,
    ): int {
        return (int) LedgerJournalBatch::query()
            ->where('ledger_journal_batches.event_type', $eventType->value)
            ->whereBetween('ledger_journal_batches.occurred_at', [$from, $to])
            ->join('ledger_entries', 'ledger_entries.batch_id', '=', 'ledger_journal_batches.id')
            ->where('ledger_entries.ledger_account', $account->value)
            ->where('ledger_entries.side', $side)
            ->sum('ledger_entries.amount_minor');
    }

    private function feeRevenueSince(Carbon $since): int
    {
        return $this->feeRevenueBetween($since, now());
    }

    private function feeRevenueBetween(Carbon $start, Carbon $end): int
    {
        return (int) LedgerEntry::query()
            ->where('ledger_account', LedgerAccount::PlatformFeeRevenue->value)
            ->where('side', 'credit')
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount_minor');
    }

    private function vatAccruedBetween(Carbon $start, Carbon $end): int
    {
        return (int) LedgerEntry::query()
            ->where('ledger_account', LedgerAccount::VatPayable->value)
            ->where('side', 'credit')
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount_minor');
    }

    private function vatPayableForQuarter(Carbon $start, Carbon $end): int
    {
        $accrued = $this->vatAccruedBetween($start, $end);
        $remitted = (int) VatRemittance::query()
            ->where('period_start', '>=', $start->toDateString())
            ->where('period_end', '<=', $end->toDateString())
            ->sum('amount_minor');

        return max(0, $accrued - $remitted);
    }

    private function quarterStart(Carbon $date): Carbon
    {
        $month = (int) (floor(($date->month - 1) / 3) * 3 + 1);

        return $date->copy()->month($month)->startOfMonth()->startOfDay();
    }

    private function quarterLabel(Carbon $date): string
    {
        $q = (int) ceil($date->month / 3);

        return 'Q'.$q.' '.$date->year;
    }

    private function percentChange(int $previous, int $current): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function categoryFilterOptions(): array
    {
        return QuestCategory::query()
            ->whereNotNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }
}
