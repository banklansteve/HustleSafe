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
            'benefits' => $this->benefitsCatalog(),
            'trust_exclusions' => $this->trustExclusionsCatalog(),
            'verification_sla' => [
                'pro_hours' => $this->kycVerificationSlaHours($user),
                'standard_hours' => PlatformSettings::freelancerStandardKycSlaHours(),
                'is_pro' => $subscription->isProActive(),
            ],
            'portfolio_limit' => [
                'max_items' => $this->maxPortfolioItems($user),
                'current_count' => $user->portfolios()->count(),
            ],
            'free_tier_highlights' => [
                [
                    'title' => __('Monthly proposal submission cap'),
                    'description' => __('Each verification level has a monthly proposal count limit (configured in Verification Engine). Pro removes this cap — job value limits still apply per tier.'),
                ],
                [
                    'title' => __('Portfolio item limit'),
                    'description' => __('Free accounts can publish up to :count portfolio items.', [
                        'count' => PlatformSettings::freelancerFreePortfolioItemLimit(),
                    ]),
                ],
                [
                    'title' => __('Standard search placement'),
                    'description' => __('You appear in matching when your skills and verification tier qualify — at the default rank.'),
                ],
                [
                    'title' => __('Standard verification turnaround'),
                    'description' => __('Identity and credential reviews typically complete within :hours hours.', [
                        'hours' => PlatformSettings::freelancerStandardKycSlaHours(),
                    ]),
                ],
            ],
            'payment_steps' => [
                __('Choose monthly or annual billing on the next screen.'),
                __('You will be redirected to Paystack to pay securely with card or bank transfer.'),
                __('Once payment is confirmed, Pro activates immediately — no manual review needed.'),
                __('Return here to see your active membership and renewal date.'),
            ],
            'pro_profile_sections' => $this->proProfileSectionsFrom($user),
        ];
    }

    /**
     * @return list<array{key: string, title: string, description: string}>
     */
    public function benefitsCatalog(): array
    {
        $proKycHours = PlatformSettings::freelancerProKycSlaHours();
        $standardKycHours = PlatformSettings::freelancerStandardKycSlaHours();
        $portfolioLimit = PlatformSettings::freelancerFreePortfolioItemLimit();

        return [
            [
                'key' => 'unlimited_proposals',
                'title' => __('Unlimited proposals per month'),
                'description' => __('No monthly proposal submission cap. You still need the verification tier required for each quest’s job value.'),
            ],
            [
                'key' => 'pro_badge',
                'title' => __('Premium freelancer badge'),
                'description' => __('A Pro badge on your public profile, search results, and proposals — visible proof of commitment, not a trust tier upgrade.'),
            ],
            [
                'key' => 'search_priority',
                'title' => __('Priority in recommendations'),
                'description' => __('Rank higher in client freelancer recommendations and quest feeds when your skills and verification tier already qualify you for the work.'),
            ],
            [
                'key' => 'quest_notifications',
                'title' => __('Featured quest notifications'),
                'description' => __('Get notified earlier for new quests in your categories when you are within your current verification tier limits.'),
            ],
            [
                'key' => 'faster_verification',
                'title' => __('Faster verification processing'),
                'description' => __('Verification reviews target :pro hours (vs :standard hours standard). Dedicated support if documents need resubmission — speeds tier progression when you are actively verifying.', [
                    'pro' => $proKycHours,
                    'standard' => $standardKycHours,
                ]),
            ],
            [
                'key' => 'portfolio_unlimited',
                'title' => __('Unlimited portfolio uploads'),
                'description' => __('Publish as many portfolio items as you need. Free accounts are limited to :count items.', ['count' => $portfolioLimit]),
            ],
            [
                'key' => 'profile_sections',
                'title' => __('Custom profile sections'),
                'description' => __('Add testimonials, media highlights, and external links to your public profile when you are Pro.'),
            ],
            [
                'key' => 'support_priority',
                'title' => __('Priority customer support'),
                'description' => __('Support chats are bumped to a higher priority queue for quicker responses.'),
            ],
        ];
    }

    /**
     * Premium must never bypass trust / verification rules.
     *
     * @return list<array{title: string, description: string}>
     */
    public function trustExclusionsCatalog(): array
    {
        return [
            [
                'title' => __('Job value tier limits'),
                'description' => __('Pro does not raise the maximum quest value you can propose on. Your verification level still controls that cap.'),
            ],
            [
                'title' => __('Account age requirements'),
                'description' => __('Pro does not skip waiting periods between verification levels. Account age gates remain in force.'),
            ],
            [
                'title' => __('Verification requirements'),
                'description' => __('Pro does not replace NIN, BVN, CAC, or other document checks. You must complete each step to advance.'),
            ],
            [
                'title' => __('Automatic tier jumps'),
                'description' => __('Pro never moves you to a higher trust tier. Faster verification processing helps only after you submit valid documents.'),
            ],
        ];
    }

    public function kycVerificationSlaHours(User $user): int
    {
        return $this->isPro($user)
            ? PlatformSettings::freelancerProKycSlaHours()
            : PlatformSettings::freelancerStandardKycSlaHours();
    }

    /**
     * null = unlimited (Pro).
     */
    public function maxPortfolioItems(User $user): ?int
    {
        return $this->isPro($user) ? null : PlatformSettings::freelancerFreePortfolioItemLimit();
    }

    public function canUseCustomProfileSections(User $user): bool
    {
        return $this->isPro($user);
    }

    /**
     * @return array{testimonials: list<array<string, string>>, external_links: list<array<string, string>>, media_links: list<array<string, string>>}
     */
    public function proProfileSectionsFrom(User $user): array
    {
        $settings = $user->public_profile_settings ?? [];

        return [
            'testimonials' => array_values(array_filter(
                is_array($settings['pro_testimonials'] ?? null) ? $settings['pro_testimonials'] : [],
                fn ($row) => is_array($row) && trim((string) ($row['quote'] ?? '')) !== '',
            )),
            'external_links' => array_values(array_filter(
                is_array($settings['pro_external_links'] ?? null) ? $settings['pro_external_links'] : [],
                fn ($row) => is_array($row) && filled($row['url'] ?? null),
            )),
            'media_links' => array_values(array_filter(
                is_array($settings['pro_media_links'] ?? null) ? $settings['pro_media_links'] : [],
                fn ($row) => is_array($row) && filled($row['url'] ?? null),
            )),
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

            app(\App\Services\Admin\PremiumPatrol\PremiumPatrolAnomalyService::class)
                ->scanAfterPremiumPayment($payment->fresh());

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
