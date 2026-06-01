<?php

namespace App\Services\Freelancer;

use App\Enums\FreelancerSubscriptionTier;
use App\Enums\LedgerAccount;
use App\Enums\LedgerEventType;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\AdminFinancialLedgerEntry;
use App\Services\Finance\DoubleEntryLedgerService;

final class FreelancerProLedgerService
{
    public function __construct(private readonly DoubleEntryLedgerService $ledger) {}

    public function recordSubscriptionPayment(FreelancerSubscriptionPayment $payment): void
    {
        AdminFinancialLedgerEntry::query()->create([
            'client_id' => null,
            'freelancer_id' => $payment->user_id,
            'type' => 'freelancer_pro_subscription',
            'direction' => 'inflow',
            'source' => 'paystack',
            'status' => 'completed',
            'description' => __('Freelancer Pro subscription payment'),
            'gross_amount_minor' => (int) $payment->amount_minor,
            'fee_amount_minor' => 0,
            'net_amount_minor' => (int) $payment->amount_minor,
            'paystack_reference' => $payment->paystack_reference,
            'meta' => [
                'billing_cycle' => $payment->billing_cycle,
                'freelancer_subscription_payment_id' => $payment->id,
            ],
            'occurred_at' => $payment->paid_at ?? now(),
        ]);

        $this->ledger->postBalancedBatch(
            LedgerEventType::FreelancerProSubscription,
            'freelancer-pro:'.$payment->id,
            [
                ['account' => LedgerAccount::PaymentGatewaySuspense, 'side' => 'debit', 'amount_minor' => (int) $payment->amount_minor],
                ['account' => LedgerAccount::PlatformFeeRevenue, 'side' => 'credit', 'amount_minor' => (int) $payment->amount_minor],
            ],
            'FreelancerProLedgerService',
            [
                'freelancer_id' => $payment->user_id,
                'paystack_reference' => $payment->paystack_reference,
                'meta' => ['billing_cycle' => $payment->billing_cycle],
            ],
            __('Freelancer Pro subscription revenue'),
        );
    }
}
