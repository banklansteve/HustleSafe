<?php

namespace App\Services\Freelancer;

use App\Enums\SubscriptionBillingCycle;
use App\Models\FreelancerSubscription;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\User;
use App\Services\Payments\PaystackClient;
use App\Support\NgnMoney;
use Illuminate\Support\Str;
use RuntimeException;

final class FreelancerProPaymentService
{
    public function __construct(private readonly PaystackClient $paystack) {}

    /**
     * @return array<string, mixed>
     */
    public function initialize(
        User $user,
        FreelancerSubscription $subscription,
        SubscriptionBillingCycle $cycle,
        int $amountMinor,
    ): array {
        $reference = 'HSPRO-'.$subscription->id.'-'.Str::lower(Str::random(8));

        $payment = FreelancerSubscriptionPayment::query()->create([
            'freelancer_subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'amount_minor' => $amountMinor,
            'billing_cycle' => $cycle->value,
            'paystack_reference' => $reference,
            'status' => 'pending',
            'meta' => ['cycle' => $cycle->value],
        ]);

        if (! $this->paystack->enabled()) {
            return [
                'authorization_url' => null,
                'reference' => $reference,
                'payment_id' => $payment->id,
                'public_key' => null,
                'amount_minor' => $amountMinor,
                'amount_display' => NgnMoney::format($amountMinor),
                'stub_mode' => true,
            ];
        }

        $init = $this->paystack->initializeTransaction([
            'email' => $user->email,
            'amount' => $amountMinor,
            'reference' => $reference,
            'currency' => 'NGN',
            'callback_url' => route('freelancer.pro.callback'),
            'metadata' => [
                'purpose' => 'freelancer_pro',
                'freelancer_subscription_id' => $subscription->id,
                'freelancer_subscription_payment_id' => $payment->id,
                'user_id' => $user->id,
                'billing_cycle' => $cycle->value,
            ],
        ]);

        $data = $init['data'] ?? [];
        if (blank($data['authorization_url'] ?? null)) {
            throw new RuntimeException(__('Unable to initialize payment. Please try again.'));
        }

        return [
            'authorization_url' => $data['authorization_url'],
            'reference' => $reference,
            'payment_id' => $payment->id,
            'public_key' => $this->paystack->publicKey(),
            'amount_minor' => $amountMinor,
            'amount_display' => NgnMoney::format($amountMinor),
            'stub_mode' => false,
        ];
    }

    public function verifyAndActivate(string $reference): FreelancerSubscription
    {
        $payment = FreelancerSubscriptionPayment::query()
            ->where('paystack_reference', $reference)
            ->firstOrFail();

        if ($payment->status === 'paid') {
            return $payment->subscription;
        }

        $payload = $this->paystack->enabled()
            ? ($this->paystack->verifyTransaction($reference)['data'] ?? [])
            : ['status' => 'success'];

        if (($payload['status'] ?? '') !== 'success') {
            throw new RuntimeException(__('Payment was not successful.'));
        }

        return app(FreelancerProSubscriptionService::class)->activateFromPayment($payment, $payload);
    }

    public function handleWebhookReference(string $reference, array $payload): void
    {
        if (! str_starts_with($reference, 'HSPRO-')) {
            return;
        }

        $payment = FreelancerSubscriptionPayment::query()
            ->where('paystack_reference', $reference)
            ->first();

        if ($payment === null || $payment->status === 'paid') {
            return;
        }

        if (($payload['status'] ?? '') !== 'success') {
            return;
        }

        app(FreelancerProSubscriptionService::class)->activateFromPayment($payment, $payload);
    }
}
