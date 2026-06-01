<?php

namespace App\Services\Finance;

use App\Enums\FinancialEscrowRecordStatus;
use App\Enums\LedgerAccount;
use App\Enums\ReconciliationExceptionStatus;
use App\Enums\ReconciliationExceptionType;
use App\Models\AdminNotification;
use App\Models\FinancialEscrowRecord;
use App\Models\FinancialReconciliationException;
use App\Models\FinancialReconciliationRun;
use App\Models\LedgerJournalBatch;
use App\Models\PaystackWebhookEvent;
use App\Models\PaymentEscrow;
use App\Models\User;
use App\Support\NgnMoney;
use Illuminate\Support\Facades\DB;

final class FinancialReconciliationService
{
    private const UNMATCHED_GRACE_MINUTES = 15;

    public function __construct(
        private readonly DoubleEntryLedgerService $ledger,
    ) {}

    public function run(): FinancialReconciliationRun
    {
        $run = FinancialReconciliationRun::query()->create([
            'started_at' => now(),
            'status' => 'running',
        ]);

        $checks = [];
        $exceptionsFound = 0;
        $recordsProcessed = 0;

        try {
            $gateway = $this->checkGatewayReconciliation();
            $checks['gateway'] = $gateway;
            $exceptionsFound += $gateway['exceptions_created'];
            $recordsProcessed += $gateway['records_processed'];

            $balance = $this->ledger->globalBalanceCheck();
            $checks['ledger_balance'] = $balance;
            $recordsProcessed++;

            if (! $balance['balanced']) {
                $this->openException(
                    ReconciliationExceptionType::LedgerImbalance,
                    __('Ledger debits and credits do not balance'),
                    __('Global ledger variance of :amount detected. All financial posting must halt until resolved.', [
                        'amount' => $this->money($balance['variance_minor']),
                    ]),
                    null,
                    null,
                    (int) $balance['variance_minor'],
                    $run,
                );
                $exceptionsFound++;
                $this->alertSuperAdminsCritical(
                    'Critical: Ledger imbalance detected',
                    'Debits and credits differ by '.$this->money($balance['variance_minor']).'. Reconciliation failed.',
                );

                $run->update([
                    'finished_at' => now(),
                    'status' => 'failed',
                    'records_processed' => $recordsProcessed,
                    'exceptions_found' => $exceptionsFound,
                    'checks' => $checks,
                    'error_message' => 'Ledger imbalance',
                ]);

                return $run->fresh();
            }

            $escrowPosition = $this->checkEscrowPosition();
            $checks['escrow_position'] = $escrowPosition;
            $recordsProcessed += $escrowPosition['records_processed'];
            if ($escrowPosition['variance_minor'] !== 0) {
                $this->openException(
                    ReconciliationExceptionType::EscrowPositionVariance,
                    __('Escrow position does not match ledger liability'),
                    __('Held escrow records total :held but Client Escrow Liability balance is :ledger — variance :variance.', [
                        'held' => $this->money($escrowPosition['held_escrow_minor']),
                        'ledger' => $this->money($escrowPosition['ledger_liability_minor']),
                        'variance' => $this->money($escrowPosition['variance_minor']),
                    ]),
                    null,
                    null,
                    (int) abs($escrowPosition['variance_minor']),
                    $run,
                );
                $exceptionsFound++;
            }

            $this->resolveStaleExceptions($run);
            $this->escalateUnassignedExceptions();

            $run->update([
                'finished_at' => now(),
                'status' => $exceptionsFound > 0 ? 'failed' : 'passed',
                'records_processed' => $recordsProcessed,
                'exceptions_found' => $exceptionsFound,
                'checks' => $checks,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $run->update([
                'finished_at' => now(),
                'status' => 'failed',
                'records_processed' => $recordsProcessed,
                'exceptions_found' => $exceptionsFound,
                'checks' => $checks,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $run->fresh();
    }

    /**
     * @return array{records_processed: int, exceptions_created: int, unmatched: list<string>, unconfirmed: list<string>}
     */
    private function checkGatewayReconciliation(): array
    {
        $exceptionsCreated = 0;
        $unmatched = [];
        $unconfirmed = [];
        $cutoff = now()->subMinutes(self::UNMATCHED_GRACE_MINUTES);

        $webhooks = PaystackWebhookEvent::query()
            ->where('event_type', 'charge.success')
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($webhooks as $webhook) {
            $reference = (string) $webhook->reference;
            if ($reference === '') {
                continue;
            }

            $escrow = PaymentEscrow::query()->where('paystack_reference', $reference)->first();
            $batchExists = LedgerJournalBatch::query()
                ->where('idempotency_key', 'ledger:escrow-funded:'.($escrow?->id ?? 0))
                ->exists();

            if ($escrow === null || ! $batchExists) {
                $fingerprint = 'unmatched:'.$reference;
                if (! $this->exceptionExists($fingerprint)) {
                    $this->openException(
                        ReconciliationExceptionType::UnmatchedInboundPayment,
                        __('Unmatched inbound Paystack payment'),
                        __('Gateway receipt :ref has no reconciled escrow funding ledger batch.', ['ref' => $reference]),
                        $escrow?->id,
                        $reference,
                        null,
                        null,
                        null,
                        $fingerprint,
                    );
                    $exceptionsCreated++;
                }
                $unmatched[] = $reference;
            }
        }

        $fundedEscrows = PaymentEscrow::query()
            ->whereIn('status', ['funded', 'held', 'released', 'partially_released', 'refunded'])
            ->whereNotNull('funded_at')
            ->get();

        foreach ($fundedEscrows as $escrow) {
            if (blank($escrow->paystack_reference)) {
                $fingerprint = 'unconfirmed:'.$escrow->id;
                if (! $this->exceptionExists($fingerprint)) {
                    $this->openException(
                        ReconciliationExceptionType::UnconfirmedEscrowFunding,
                        __('Escrow funded without gateway reference'),
                        __('Escrow :ref is marked funded but has no Paystack reference on file.', ['ref' => $escrow->reference]),
                        $escrow->id,
                        null,
                        null,
                        null,
                        null,
                        $fingerprint,
                    );
                    $exceptionsCreated++;
                }
                $unconfirmed[] = $escrow->reference;
            }
        }

        return [
            'records_processed' => $webhooks->count() + $fundedEscrows->count(),
            'exceptions_created' => $exceptionsCreated,
            'unmatched' => $unmatched,
            'unconfirmed' => $unconfirmed,
        ];
    }

    /**
     * @return array{records_processed: int, held_escrow_minor: int, ledger_liability_minor: int, variance_minor: int}
     */
    private function checkEscrowPosition(): array
    {
        $heldMinor = (int) FinancialEscrowRecord::query()
            ->where('status', FinancialEscrowRecordStatus::Held->value)
            ->sum('total_funded_minor');

        $ledgerLiability = abs($this->ledger->accountBalanceMinor(LedgerAccount::ClientEscrowLiability));
        $variance = $heldMinor - $ledgerLiability;

        return [
            'records_processed' => FinancialEscrowRecord::query()->where('status', FinancialEscrowRecordStatus::Held->value)->count(),
            'held_escrow_minor' => $heldMinor,
            'ledger_liability_minor' => $ledgerLiability,
            'variance_minor' => $variance,
        ];
    }

    private function openException(
        ReconciliationExceptionType $type,
        string $title,
        string $description,
        ?int $paymentEscrowId,
        ?string $paystackReference,
        ?int $varianceMinor,
        ?FinancialReconciliationRun $run,
        ?string $fingerprint = null,
    ): FinancialReconciliationException {
        $meta = $fingerprint ? ['fingerprint' => $fingerprint] : null;

        $existing = null;
        if ($fingerprint) {
            $existing = FinancialReconciliationException::query()
                ->where('status', '!=', ReconciliationExceptionStatus::Resolved->value)
                ->where('meta->fingerprint', $fingerprint)
                ->first();
        }

        if ($existing !== null) {
            $existing->update(['latest_run_id' => $run?->id]);

            return $existing;
        }

        return FinancialReconciliationException::query()->create([
            'first_run_id' => $run?->id,
            'latest_run_id' => $run?->id,
            'type' => $type->value,
            'status' => ReconciliationExceptionStatus::Open->value,
            'payment_escrow_id' => $paymentEscrowId,
            'paystack_reference' => $paystackReference,
            'variance_minor' => $varianceMinor,
            'title' => $title,
            'description' => $description,
            'first_detected_at' => now(),
            'meta' => $meta,
        ]);
    }

    private function exceptionExists(string $fingerprint): bool
    {
        return FinancialReconciliationException::query()
            ->where('status', '!=', ReconciliationExceptionStatus::Resolved->value)
            ->where('meta->fingerprint', $fingerprint)
            ->exists();
    }

    private function resolveStaleExceptions(FinancialReconciliationRun $run): void
    {
        FinancialReconciliationException::query()
            ->where('status', ReconciliationExceptionStatus::Resolved->value)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    private function escalateUnassignedExceptions(): void
    {
        $stale = FinancialReconciliationException::query()
            ->whereIn('status', [
                ReconciliationExceptionStatus::Open->value,
                ReconciliationExceptionStatus::UnderInvestigation->value,
            ])
            ->whereNull('assigned_to_user_id')
            ->where('first_detected_at', '<=', now()->subDay())
            ->whereNull('escalated_at')
            ->get();

        foreach ($stale as $exception) {
            $exception->update(['escalated_at' => now()]);
            $this->alertSuperAdminsCritical(
                'Reconciliation exception unassigned for 24+ hours',
                $exception->title.' — assign an owner in the Financial Audit exceptions queue.',
                route('admin.financial-audit.exceptions.index'),
            );
        }
    }

    private function alertSuperAdminsCritical(string $title, string $body, ?string $url = null): void
    {
        $superAdmins = User::query()->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->get();

        foreach ($superAdmins as $admin) {
            AdminNotification::query()->create([
                'admin_user_id' => $admin->id,
                'category' => 'financial_audit',
                'priority' => 'critical',
                'title' => $title,
                'body' => $body,
                'action_label' => 'Open financial audit',
                'action_url' => $url ?? route('admin.financial-audit.index'),
            ]);
        }
    }

    private function money(int $minor): string
    {
        return NgnMoney::format($minor);
    }
}
