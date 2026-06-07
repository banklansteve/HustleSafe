<?php

namespace App\Services\Quest;

use App\Enums\QuestBoostTier;
use App\Models\Quest;
use App\Models\QuestBoostPayment;
use App\Models\User;
use App\Services\Admin\QuestBoostService;
use App\Services\Payments\PaystackClient;
use App\Support\NgnMoney;
use App\Support\PlatformSettings;
use Illuminate\Support\Str;
use RuntimeException;

final class QuestBoostPaymentService
{
    public function __construct(
        private readonly PaystackClient $paystack,
        private readonly ClientQuestBoostService $clientBoosts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function initialize(Quest $quest, User $client, QuestBoostTier $tier): array
    {
        if (! $this->clientBoosts->canPurchase($quest, $client)) {
            throw new RuntimeException(__('This quest cannot be boosted right now.'));
        }

        $this->clientBoosts->assertTierAllowed($quest, $tier);

        $amountMinor = PlatformSettings::questBoostPriceMinor($tier);
        $reference = 'HSQB-'.$quest->id.'-'.Str::lower(Str::random(8));

        $payment = QuestBoostPayment::query()->create([
            'quest_id' => $quest->id,
            'client_id' => $client->id,
            'tier' => $tier->value,
            'amount_minor' => $amountMinor,
            'paystack_reference' => $reference,
            'status' => 'pending',
            'meta' => [
                'tier_label' => $tier->label(),
                'listing_expires_at' => $quest->listing_expires_at?->toIso8601String(),
            ],
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
                'quest_route_key' => $quest->getRouteKey(),
            ];
        }

        $init = $this->paystack->initializeTransaction([
            'email' => $client->email,
            'amount' => $amountMinor,
            'reference' => $reference,
            'currency' => 'NGN',
            'callback_url' => route('payments.quest-boost.callback'),
            'metadata' => [
                'purpose' => 'quest_boost',
                'quest_id' => $quest->id,
                'quest_boost_payment_id' => $payment->id,
                'client_id' => $client->id,
                'tier' => $tier->value,
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
            'quest_route_key' => $quest->getRouteKey(),
        ];
    }

    public function verifyAndActivate(string $reference): QuestBoostPayment
    {
        $payment = QuestBoostPayment::query()
            ->with(['quest', 'client'])
            ->where('paystack_reference', $reference)
            ->firstOrFail();

        if ($payment->status === 'paid') {
            return $payment;
        }

        $payload = $this->paystack->enabled()
            ? ($this->paystack->verifyTransaction($reference)['data'] ?? [])
            : ['status' => 'success'];

        if (($payload['status'] ?? '') !== 'success') {
            throw new RuntimeException(__('Payment was not successful.'));
        }

        app(QuestBoostService::class)->activateFromPayment($payment, $payload);

        return $payment->fresh(['questBoost']);
    }

    public function handleWebhookReference(string $reference, array $payload): void
    {
        if (! str_starts_with($reference, 'HSQB-')) {
            return;
        }

        $payment = QuestBoostPayment::query()
            ->where('paystack_reference', $reference)
            ->first();

        if ($payment === null || $payment->status === 'paid') {
            return;
        }

        if (($payload['status'] ?? '') !== 'success') {
            return;
        }

        app(QuestBoostService::class)->activateFromPayment($payment, $payload);
    }
}
