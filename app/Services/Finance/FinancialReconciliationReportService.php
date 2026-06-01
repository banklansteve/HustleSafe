<?php

namespace App\Services\Finance;

use App\Enums\FinancialEscrowRecordStatus;
use App\Enums\LedgerAccount;
use App\Enums\LedgerEventType;
use App\Models\FinancialEscrowRecord;
use App\Models\FinancialReconciliationRun;
use App\Support\NgnMoney;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FinancialReconciliationReportService
{
    public function __construct(
        private readonly FinancialAuditDashboardService $dashboard,
        private readonly DoubleEntryLedgerService $ledger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(Request $request): array
    {
        [$from, $to] = $this->dashboard->resolvePeriod($request);
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->query('as_of'))->endOfDay()
            : now()->endOfDay();

        $period = $this->periodTotals($from, $to);
        $asOfPosition = $this->escrowPositionAsOf($asOf);

        $lastRun = FinancialReconciliationRun::query()->latest('id')->first();

        return [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'as_of' => $asOf->toDateString(),
                'label' => $from->format('d M Y').' – '.$to->format('d M Y'),
            ],
            'period' => $period,
            'as_of_position' => $asOfPosition,
            'reconciliation' => [
                'last_run_at' => $lastRun?->finished_at?->toIso8601String(),
                'last_run_status' => $lastRun?->status,
                'ledger_balanced' => $this->ledger->globalBalanceCheck()['balanced'],
            ],
            'recent_runs' => FinancialReconciliationRun::query()
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn ($run) => [
                    'id' => $run->id,
                    'status' => $run->status,
                    'passed' => $run->status === 'passed',
                    'started_at' => $run->started_at?->toIso8601String(),
                    'duration_seconds' => $run->started_at && $run->finished_at
                        ? max(0, (int) $run->started_at->diffInSeconds($run->finished_at))
                        : null,
                    'records_processed' => (int) $run->records_processed,
                    'exceptions_found' => (int) $run->exceptions_found,
                ])
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(Request $request): array
    {
        $payload = $this->report($request);

        return [
            'summary' => $payload['period'],
            'as_of' => $payload['as_of_position'],
            'held_contracts' => $payload['as_of_position']['contracts'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodTotals(Carbon $from, Carbon $to): array
    {
        $escrowFunded = (int) FinancialEscrowRecord::query()
            ->whereBetween('funded_at', [$from, $to])
            ->sum('total_funded_minor');

        $fundingCount = (int) FinancialEscrowRecord::query()
            ->whereBetween('funded_at', [$from, $to])
            ->count();

        $platformFees = $this->sumLedgerCredits(LedgerAccount::PlatformFeeRevenue, $from, $to);
        $vatAccrued = $this->sumLedgerCredits(LedgerAccount::VatPayable, $from, $to);

        $releasedToFreelancers = $this->sumLedgerEventCredits(
            LedgerAccount::FreelancerPayable,
            LedgerEventType::EscrowReleased,
            $from,
            $to,
        );

        $releasedGross = $this->sumLedgerEventDebits(
            LedgerAccount::ClientEscrowLiability,
            LedgerEventType::EscrowReleased,
            $from,
            $to,
        );

        $refunded = $this->sumLedgerEventCredits(
            LedgerAccount::RefundPayable,
            LedgerEventType::DisputeRefund,
            $from,
            $to,
        );

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
            'released_gross_minor' => $releasedGross,
            'released_gross_display' => NgnMoney::format($releasedGross),
            'released_to_freelancers_minor' => $releasedToFreelancers,
            'released_to_freelancers_display' => NgnMoney::format($releasedToFreelancers),
            'released_count' => $releasedCount,
            'refunded_minor' => $refunded,
            'refunded_display' => NgnMoney::format($refunded),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function escrowPositionAsOf(Carbon $asOf): array
    {
        $end = $asOf->copy()->endOfDay();
        $query = $this->heldAsOfQuery($end);

        $total = (int) (clone $query)->sum('total_funded_minor');
        $count = (int) (clone $query)->count();

        $contracts = (clone $query)
            ->with(['contract:id,agreed_delivery_date,reference_code', 'category:id,name'])
            ->orderBy('funded_at')
            ->limit(200)
            ->get()
            ->map(fn (FinancialEscrowRecord $r) => $this->contractRow($r))
            ->all();

        return [
            'as_of' => $asOf->toDateString(),
            'as_of_label' => $asOf->format('d M Y'),
            'total_held_minor' => $total,
            'total_held_display' => NgnMoney::format($total),
            'active_count' => $count,
            'contracts' => $contracts,
        ];
    }

    private function heldAsOfQuery(Carbon $end): Builder
    {
        return FinancialEscrowRecord::query()
            ->whereNotNull('funded_at')
            ->where('funded_at', '<=', $end)
            ->where(function (Builder $q) use ($end): void {
                $q->whereNull('released_at')
                    ->orWhere('released_at', '>', $end);
            })
            ->where(function (Builder $q) use ($end): void {
                $q->whereNull('refunded_at')
                    ->orWhere('refunded_at', '>', $end);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function contractRow(FinancialEscrowRecord $r): array
    {
        $dueDate = $r->contract?->agreed_delivery_date;

        return [
            'id' => $r->id,
            'escrow_reference' => $r->escrow_reference,
            'contract_reference' => $r->contract_reference,
            'quest_title' => $r->quest_title,
            'client_name' => $r->client_name,
            'freelancer_name' => $r->freelancer_name,
            'status' => $r->status,
            'status_label' => $r->statusEnum()->label(),
            'funded_at' => $r->funded_at?->toIso8601String(),
            'due_date_label' => $dueDate?->format('d M Y'),
            'funded_minor' => (int) $r->total_funded_minor,
            'platform_fee_minor' => (int) $r->platform_fee_minor,
            'vat_minor' => (int) $r->vat_minor,
            'freelancer_net_minor' => (int) $r->freelancer_net_minor,
            'funded_display' => NgnMoney::format((int) $r->total_funded_minor),
            'platform_fee_display' => NgnMoney::format((int) $r->platform_fee_minor),
            'vat_display' => NgnMoney::format((int) $r->vat_minor),
            'freelancer_net_display' => NgnMoney::format((int) $r->freelancer_net_minor),
        ];
    }

    private function sumLedgerCredits(LedgerAccount $account, Carbon $from, Carbon $to): int
    {
        return (int) DB::table('ledger_entries')
            ->where('ledger_account', $account->value)
            ->where('side', 'credit')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('amount_minor');
    }

    private function sumLedgerEventCredits(LedgerAccount $account, LedgerEventType $event, Carbon $from, Carbon $to): int
    {
        return (int) DB::table('ledger_journal_batches')
            ->join('ledger_entries', 'ledger_entries.batch_id', '=', 'ledger_journal_batches.id')
            ->where('ledger_journal_batches.event_type', $event->value)
            ->whereBetween('ledger_journal_batches.occurred_at', [$from, $to])
            ->where('ledger_entries.ledger_account', $account->value)
            ->where('ledger_entries.side', 'credit')
            ->sum('ledger_entries.amount_minor');
    }

    private function sumLedgerEventDebits(LedgerAccount $account, LedgerEventType $event, Carbon $from, Carbon $to): int
    {
        return (int) DB::table('ledger_journal_batches')
            ->join('ledger_entries', 'ledger_entries.batch_id', '=', 'ledger_journal_batches.id')
            ->where('ledger_journal_batches.event_type', $event->value)
            ->whereBetween('ledger_journal_batches.occurred_at', [$from, $to])
            ->where('ledger_entries.ledger_account', $account->value)
            ->where('ledger_entries.side', 'debit')
            ->sum('ledger_entries.amount_minor');
    }
}
