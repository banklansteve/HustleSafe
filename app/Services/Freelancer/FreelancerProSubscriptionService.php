<?php

namespace App\Services\Freelancer;

use App\Enums\FreelancerSubscriptionStatus;
use App\Enums\FreelancerSubscriptionTier;
use App\Enums\SubscriptionBillingCycle;
use App\Models\FreelancerSubscription;
use App\Models\FreelancerSubscriptionHistory;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\User;
use App\Notifications\FreelancerProSubscriptionConfirmedNotification;
use App\Support\NgnMoney;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FreelancerProSubscriptionService
{
    public function __construct(
        private readonly FreelancerProPaymentService $payments,
    ) {}

    public function subscriptionFor(User $user): FreelancerSubscription
    {
        return FreelancerSubscription::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'status' => FreelancerSubscriptionStatus::Active->value,
                'tier' => FreelancerSubscriptionTier::Free->value,
                'auto_renew' => false,
            ],
        );
    }

    public function isPro(User $user): bool
    {
        $subscription = $this->subscriptionFor($user);

        if (! $subscription->isProActive()) {
            $this->expireIfNeeded($subscription);

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardPayload(User $user): array
    {
        $subscription = $this->subscriptionFor($user);
        $this->expireIfNeeded($subscription);
        $subscription->refresh();

        $pricing = PlatformSettings::freelancerProPricing();
        $quota = app(ProposalQuotaService::class)->usagePayload($user);

        return [
            'subscription' => [
                'tier' => $subscription->tier,
                'tier_label' => $subscription->tierEnum()->label(),
                'status' => $subscription->status,
                'status_label' => $subscription->statusEnum()->label(),
                'is_pro' => $subscription->isProActive(),
                'started_at' => $subscription->started_at?->toIso8601String(),
                'renewal_date' => $subscription->renewal_date?->toIso8601String(),
                'billing_cycle' => $subscription->billing_cycle,
                'billing_cycle_label' => $subscription->billingCycleEnum()?->label(),
                'auto_renew' => (bool) $subscription->auto_renew,
                'total_spent_display' => NgnMoney::format((int) $subscription->total_spent_minor),
                'payment_method' => $subscription->payment_method_snapshot,
                'next_charge_minor' => $subscription->isProActive()
                    ? ($subscription->billing_cycle === SubscriptionBillingCycle::Year->value
                        ? $pricing['annual_minor']
                        : $pricing['monthly_minor'])
                    : null,
            ],
            'pricing' => [
                'monthly_minor' => $pricing['monthly_minor'],
                'monthly_display' => NgnMoney::format($pricing['monthly_minor']),
                'annual_minor' => $pricing['annual_minor'],
                'annual_display' => NgnMoney::format($pricing['annual_minor']),
                'annual_savings_percent' => $pricing['monthly_minor'] > 0
                    ? round((1 - ($pricing['annual_minor'] / ($pricing['monthly_minor'] * 12))) * 100, 2)
                    : 0,
            ],
            'quota' => $quota,
            'benefits' => [
                __('Unlimited proposal submissions per month'),
                __('Verified Pro badge on your profile'),
                __('Priority placement in category search results'),
                __('Early access to invite-only quests'),
                __('Pro Member label on your proposals'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function beginUpgrade(User $user, string $billingCycle): array
    {
        if ($user->role?->slug !== 'freelancer') {
            throw ValidationException::withMessages(['billing_cycle' => [__('Only freelancers can upgrade to Pro.')]]);
        }

        $cycle = SubscriptionBillingCycle::from($billingCycle);
        $pricing = PlatformSettings::freelancerProPricing();
        $amountMinor = $cycle === SubscriptionBillingCycle::Year
            ? $pricing['annual_minor']
            : $pricing['monthly_minor'];

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['billing_cycle' => [__('Pro pricing is not configured.')]]);
        }

        $subscription = $this->subscriptionFor($user);
        if ($subscription->isProActive()) {
            throw ValidationException::withMessages(['billing_cycle' => [__('You already have an active Pro subscription.')]]);
        }

        return $this->payments->initialize($user, $subscription, $cycle, $amountMinor);
    }

    public function activateFromPayment(FreelancerSubscriptionPayment $payment, array $gatewayData = []): FreelancerSubscription
    {
        return DB::transaction(function () use ($payment, $gatewayData): FreelancerSubscription {
            $payment->refresh();
            if ($payment->status === 'paid') {
                return $payment->subscription;
            }

            $subscription = $payment->subscription()->lockForUpdate()->firstOrFail();
            $cycle = SubscriptionBillingCycle::from($payment->billing_cycle);
            $renewal = $cycle === SubscriptionBillingCycle::Year
                ? now()->addYear()
                : now()->addMonth();

            $fromStatus = $subscription->status;
            $pricing = PlatformSettings::freelancerProPricing();
        $subscription->forceFill([
                'status' => FreelancerSubscriptionStatus::Active->value,
                'tier' => FreelancerSubscriptionTier::Pro->value,
                'started_at' => now(),
                'renewal_date' => $renewal,
                'billing_cycle' => $cycle->value,
                'monthly_price_minor' => $pricing['monthly_minor'],
                'annual_price_minor' => $pricing['annual_minor'],
                'auto_renew' => false,
                'total_spent_minor' => (int) $subscription->total_spent_minor + (int) $payment->amount_minor,
                'payment_method_snapshot' => [
                    'channel' => data_get($gatewayData, 'channel'),
                    'brand' => data_get($gatewayData, 'authorization.brand'),
                    'last4' => data_get($gatewayData, 'authorization.last4'),
                    'exp_month' => data_get($gatewayData, 'authorization.exp_month'),
                    'exp_year' => data_get($gatewayData, 'authorization.exp_year'),
                ],
            ])->save();

            $payment->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'meta' => array_merge($payment->meta ?? [], ['gateway' => $gatewayData]),
            ])->save();

            $this->logHistory($subscription, 'subscription_activated', $fromStatus, $subscription->status, $subscription->user_id, [
                'payment_id' => $payment->id,
                'paystack_reference' => $payment->paystack_reference,
                'amount_minor' => $payment->amount_minor,
            ]);

            app(FreelancerProLedgerService::class)->recordSubscriptionPayment($payment);

            $subscription->user->notify(new FreelancerProSubscriptionConfirmedNotification($subscription));

            return $subscription->fresh();
        });
    }

    public function cancel(User $user, ?string $reason = null): FreelancerSubscription
    {
        $subscription = $this->subscriptionFor($user);
        if ($subscription->tier !== FreelancerSubscriptionTier::Pro->value) {
            throw ValidationException::withMessages(['subscription' => [__('You do not have an active Pro subscription to cancel.')]]);
        }

        $fromStatus = $subscription->status;
        $subscription->forceFill([
            'status' => FreelancerSubscriptionStatus::Cancelled->value,
            'tier' => FreelancerSubscriptionTier::Free->value,
            'auto_renew' => false,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'renewal_date' => null,
        ])->save();

        $this->logHistory($subscription, 'subscription_cancelled', $fromStatus, $subscription->status, $user->id, [
            'reason' => $reason,
        ]);

        return $subscription->fresh();
    }

    public function expireIfNeeded(FreelancerSubscription $subscription): void
    {
        if ($subscription->tier !== FreelancerSubscriptionTier::Pro->value) {
            return;
        }

        if ($subscription->status !== FreelancerSubscriptionStatus::Active->value) {
            return;
        }

        if ($subscription->renewal_date === null || $subscription->renewal_date->isFuture()) {
            return;
        }

        $fromStatus = $subscription->status;
        $subscription->forceFill([
            'status' => FreelancerSubscriptionStatus::Expired->value,
            'tier' => FreelancerSubscriptionTier::Free->value,
        ])->save();

        $this->logHistory($subscription, 'subscription_expired', $fromStatus, $subscription->status, null, []);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function logHistory(
        FreelancerSubscription $subscription,
        string $event,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorUserId,
        array $meta,
    ): void {
        FreelancerSubscriptionHistory::query()->create([
            'freelancer_subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_user_id' => $actorUserId,
            'meta' => $meta,
            'occurred_at' => now(),
        ]);
    }
}
