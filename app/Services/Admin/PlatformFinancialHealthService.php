<?php

namespace App\Services\Admin;

use App\Models\AdminFinancialLedgerEntry;
use App\Models\PaymentEscrow;
use App\Models\WalletWithdrawal;
use App\Services\Payments\PaymentMonitoringAnomalyEngine;
use App\Support\NgnMoney;
use Illuminate\Support\Facades\Schema;

class PlatformFinancialHealthService
{
    public function __construct(
        private readonly PaymentMonitoringAnomalyEngine $anomalies,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $tz = config('app.timezone', 'Africa/Lagos');
        $now = now($tz);
        $todayStart = $now->copy()->startOfDay()->utc();
        $monthStart = $now->copy()->startOfMonth()->utc();
        $yearStart = $now->copy()->startOfYear()->utc();

        $escrowHeldMinor = $this->totalEscrowHeldMinor();
        $pendingWithdrawalsMinor = $this->pendingWithdrawalsMinor();

        $feeQuery = AdminFinancialLedgerEntry::query()
            ->where('type', 'platform_fee')
            ->where('fee_amount_minor', '>', 0);

        $payoutQuery = AdminFinancialLedgerEntry::query()->where('type', 'payout');

        $anomalyItems = $this->anomalies->detectAll();
        $highAnomalies = $anomalyItems->where('severity', 'high')->count();

        return [
            'metrics' => [
                'escrow_held' => NgnMoney::format($escrowHeldMinor),
                'escrow_held_minor' => $escrowHeldMinor,
                'pending_withdrawals' => NgnMoney::format($pendingWithdrawalsMinor),
                'pending_withdrawals_minor' => $pendingWithdrawalsMinor,
                'platform_fees_today' => NgnMoney::format((int) (clone $feeQuery)->where('occurred_at', '>=', $todayStart)->sum('fee_amount_minor')),
                'platform_fees_month' => NgnMoney::format((int) (clone $feeQuery)->where('occurred_at', '>=', $monthStart)->sum('fee_amount_minor')),
                'platform_fees_year' => NgnMoney::format((int) (clone $feeQuery)->where('occurred_at', '>=', $yearStart)->sum('fee_amount_minor')),
                'payout_volume_today' => NgnMoney::format((int) (clone $payoutQuery)->where('occurred_at', '>=', $todayStart)->sum('net_amount_minor')),
                'payout_volume_month' => NgnMoney::format((int) (clone $payoutQuery)->where('occurred_at', '>=', $monthStart)->sum('net_amount_minor')),
                'financial_anomalies' => $anomalyItems->count(),
                'financial_anomalies_high' => $highAnomalies,
            ],
            'anomaly_preview' => $anomalyItems->take(5)->map(fn (array $row) => [
                'label' => $row['anomaly_label'] ?? $row['anomaly_type'] ?? 'Anomaly',
                'severity' => $row['severity'] ?? 'medium',
                'quest_title' => $row['quest_title'] ?? $row['quest']['title'] ?? null,
                'detected_at' => $row['detected_at'] ?? now()->toIso8601String(),
            ])->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function totalEscrowHeldMinor(): int
    {
        if (Schema::hasTable('payment_escrows')) {
            return (int) PaymentEscrow::query()
                ->whereIn('status', ['funded', 'held', 'partially_released'])
                ->sum('amount_minor');
        }

        return (int) \App\Models\Quest::query()
            ->whereIn('escrow_status', ['funded', 'partially_released', 'held', 'frozen'])
            ->sum('budget_amount_minor');
    }

    private function pendingWithdrawalsMinor(): int
    {
        if (! Schema::hasTable('wallet_withdrawals')) {
            return 0;
        }

        return (int) WalletWithdrawal::query()
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount_minor');
    }
}
