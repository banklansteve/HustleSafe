<?php

namespace App\Services\Payments;

use App\Enums\QuestStatus;
use App\Models\AdminFinancialLedgerEntry;
use App\Models\PaymentEscrow;
use App\Models\PaystackWebhookEvent;
use App\Models\Quest;
use App\Models\QuestFundingIntent;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\ProposalEscrowFundedFreelancerNotification;
use App\Services\Admin\AdminActivityFeedService;
use App\Support\NgnMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EscrowPaymentService
{
    public function __construct(
        private readonly PaystackClient $paystack,
        private readonly WalletService $wallets,
    ) {}

    public function driverEnabled(): bool
    {
        return $this->paystack->enabled() || (string) config('escrow.driver') === 'stub';
    }

    /**
     * @return array{authorization_url: ?string, reference: string, escrow_id: int, public_key: ?string, amount_minor: int, amount_display: string}
     */
    public function beginQuestFunding(Quest $quest, QuestOffer $offer, User $client): array
    {
        $this->assertFundingPreconditions($quest, $offer, $client);

        $amountMinor = $this->quotedMinor($offer, $quest);
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => [__('Escrow amount must be greater than zero.')]]);
        }

        $escrow = DB::transaction(function () use ($quest, $offer, $client, $amountMinor): PaymentEscrow {
            $escrow = PaymentEscrow::query()->firstOrCreate(
                ['quest_id' => $quest->id],
                [
                    'quest_offer_id' => $offer->id,
                    'client_id' => $client->id,
                    'freelancer_id' => (int) $offer->freelancer_id,
                    'amount_minor' => $amountMinor,
                    'currency' => (string) config('payment.currency', 'NGN'),
                    'status' => 'pending',
                ],
            );

            if ($escrow->status === 'funded') {
                throw ValidationException::withMessages(['escrow' => [__('Escrow is already funded for this quest.')]]);
            }

            $escrow->update([
                'quest_offer_id' => $offer->id,
                'freelancer_id' => (int) $offer->freelancer_id,
                'amount_minor' => $amountMinor,
                'status' => 'pending',
            ]);

            return $escrow;
        });

        $reference = 'HSE-'.$escrow->reference.'-'.Str::lower(Str::random(6));

        QuestFundingIntent::query()->create([
            'quest_id' => $quest->id,
            'quest_offer_id' => $offer->id,
            'initiated_by_user_id' => $client->id,
            'quoted_total_minor' => $amountMinor,
            'status' => 'initiated',
            'gateway_key' => $this->paystack->enabled() ? 'paystack' : 'stub',
            'payment_escrow_id' => $escrow->id,
            'paystack_reference' => $reference,
            'meta' => ['escrow_reference' => $escrow->reference],
        ]);

        if (! $this->paystack->enabled()) {
            return [
                'authorization_url' => null,
                'reference' => $reference,
                'escrow_id' => $escrow->id,
                'public_key' => null,
                'amount_minor' => $amountMinor,
                'amount_display' => NgnMoney::format($amountMinor),
                'stub_mode' => true,
            ];
        }

        $init = $this->paystack->initializeTransaction([
            'email' => $client->email,
            'amount' => $amountMinor,
            'reference' => $reference,
            'currency' => 'NGN',
            'callback_url' => route('payments.paystack.callback'),
            'metadata' => [
                'quest_id' => $quest->id,
                'quest_offer_id' => $offer->id,
                'payment_escrow_id' => $escrow->id,
                'client_id' => $client->id,
                'custom_fields' => [
                    [
                        'display_name' => 'Quest',
                        'variable_name' => 'quest_title',
                        'value' => Str::limit($quest->title, 80),
                    ],
                ],
            ],
        ]);

        $data = $init['data'] ?? [];
        $escrow->update([
            'paystack_reference' => $reference,
            'paystack_access_code' => $data['access_code'] ?? null,
        ]);

        return [
            'authorization_url' => $data['authorization_url'] ?? null,
            'reference' => $reference,
            'escrow_id' => $escrow->id,
            'public_key' => $this->paystack->publicKey(),
            'amount_minor' => $amountMinor,
            'amount_display' => NgnMoney::format($amountMinor),
            'stub_mode' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(string $eventId, string $eventType, array $payload): void
    {
        $existing = PaystackWebhookEvent::query()->where('event_id', $eventId)->first();
        if ($existing?->processed_at !== null) {
            return;
        }

        $event = $existing ?? PaystackWebhookEvent::query()->create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'reference' => data_get($payload, 'data.reference'),
            'payload' => $payload,
        ]);

        try {
            match ($eventType) {
                'charge.success' => $this->handleChargeSuccess($payload),
                'charge.failed' => null,
                'transfer.success' => $this->handleTransferSuccess($payload),
                'transfer.failed', 'transfer.reversed' => $this->handleTransferFailed($payload),
                default => null,
            };
            $event->update([
                'processed_at' => now(),
                'processing_result' => 'ok',
            ]);
        } catch (\Throwable $e) {
            $event->update([
                'processed_at' => now(),
                'processing_result' => 'error',
                'processing_error' => $e->getMessage(),
            ]);
            report($e);
            throw $e;
        }
    }

    public function verifyAndFund(string $reference): PaymentEscrow
    {
        if (! $this->paystack->enabled()) {
            throw new RuntimeException(__('Paystack is not enabled.'));
        }

        $verified = $this->paystack->verifyTransaction($reference);
        $data = $verified['data'] ?? [];
        if (($data['status'] ?? '') !== 'success') {
            throw ValidationException::withMessages(['payment' => [__('Payment was not successful yet.')]]);
        }

        return $this->markEscrowFundedFromPaystack($reference, $data);
    }

    /**
     * @param  array<string, mixed>  $paystackData
     */
    public function markEscrowFundedFromPaystack(string $reference, array $paystackData): PaymentEscrow
    {
        $idempotencyKey = 'paystack:fund:'.$reference;

        return DB::transaction(function () use ($reference, $paystackData, $idempotencyKey): PaymentEscrow {
            $escrow = PaymentEscrow::query()
                ->where('paystack_reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($escrow === null) {
                $meta = $paystackData['metadata'] ?? [];
                if (is_string($meta)) {
                    $meta = json_decode($meta, true) ?: [];
                }
                $escrowId = (int) ($meta['payment_escrow_id'] ?? 0);
                $escrow = $escrowId > 0
                    ? PaymentEscrow::query()->lockForUpdate()->find($escrowId)
                    : null;
            }

            if ($escrow === null) {
                throw ValidationException::withMessages(['payment' => [__('Escrow record not found for this payment.')]]);
            }

            if ($escrow->status === 'funded') {
                return $escrow;
            }

            $paidMinor = (int) ($paystackData['amount'] ?? $escrow->amount_minor);
            if ($paidMinor < (int) $escrow->amount_minor) {
                throw ValidationException::withMessages(['payment' => [__('Paid amount is less than required escrow total.')]]);
            }

            $escrow->update([
                'status' => 'funded',
                'paystack_reference' => $reference,
                'funded_at' => now(),
            ]);

            $quest = $escrow->quest()->lockForUpdate()->first();
            if ($quest !== null) {
                $quest->update([
                    'escrow_status' => 'funded',
                    'status' => QuestStatus::InProgress,
                    'escrow_funded_at' => now(),
                ]);
                $quest->refresh();
                app(\App\Services\QuestCompletionEventLogger::class)->record(
                    $quest,
                    'escrow_funded',
                    null,
                    null,
                    [
                        'paystack_reference' => $reference,
                        'amount_minor' => (int) $escrow->amount_minor,
                    ],
                );
            }

            $quest->refresh();
            if ($quest !== null) {
                app(\App\Services\Contracts\ContractLifecycleService::class)->activateFromEscrowFunding($quest, $escrow->fresh());
            }

            $client = User::query()->find($escrow->client_id);
            if ($client !== null) {
                $this->wallets->recordAudit(
                    $client,
                    (int) $escrow->amount_minor,
                    'escrow_hold',
                    $idempotencyKey.':client',
                    ['escrow_reference' => $escrow->reference],
                    $escrow->id,
                    $escrow->quest_id,
                    $reference,
                    __('Escrow funded via Paystack'),
                );
            }

            AdminFinancialLedgerEntry::query()->create([
                'quest_id' => $escrow->quest_id,
                'quest_offer_id' => $escrow->quest_offer_id,
                'client_id' => $escrow->client_id,
                'freelancer_id' => $escrow->freelancer_id,
                'type' => 'escrow_funding',
                'direction' => 'credit',
                'source' => 'paystack',
                'description' => __('Escrow funded via Paystack'),
                'gross_amount_minor' => (int) $escrow->amount_minor,
                'fee_amount_minor' => 0,
                'net_amount_minor' => (int) $escrow->amount_minor,
                'balance_after_minor' => (int) $escrow->amount_minor,
                'paystack_reference' => $reference,
                'meta' => ['payment_escrow_id' => $escrow->id],
                'occurred_at' => now(),
            ]);

            app(\App\Services\Finance\FinancialLedgerBridgeService::class)
                ->onEscrowFunded($escrow->fresh(), $reference);

            QuestFundingIntent::query()
                ->where('paystack_reference', $reference)
                ->update(['status' => 'completed']);

            $offer = QuestOffer::query()->find($escrow->quest_offer_id);
            if ($offer !== null) {
                $offer->freelancer?->notify(new ProposalEscrowFundedFreelancerNotification($offer));
            }

            return $escrow->fresh(['quest', 'client', 'freelancer']);
        });
    }

    public function releaseEscrowToWallet(
        Quest $quest,
        ?User $actor = null,
        ?string $reason = null,
        bool $ignorePolicy = false,
        ?string $releaseTrigger = null,
    ): PaymentEscrow {
        if (! $ignorePolicy && ! \App\Support\EscrowReleasePolicy::canReleaseFunds($quest, $actor)) {
            throw ValidationException::withMessages([
                'escrow' => [\App\Support\EscrowReleasePolicy::blockedReleaseReason($quest, $actor)],
            ]);
        }

        $escrow = PaymentEscrow::query()->where('quest_id', $quest->id)->lockForUpdate()->first();
        if ($escrow === null || ! $escrow->isFunded()) {
            throw ValidationException::withMessages(['escrow' => [__('Escrow is not funded or does not exist.')]]);
        }

        $releasable = $escrow->releasableMinor();
        if ($releasable <= 0) {
            throw ValidationException::withMessages(['escrow' => [__('Nothing left to release on this escrow.')]]);
        }

        $feeMinor = NgnMoney::platformFeeMinor($releasable);
        $netMinor = max(0, $releasable - $feeMinor);
        $freelancer = User::query()->find($escrow->freelancer_id);
        if ($freelancer === null) {
            throw ValidationException::withMessages(['freelancer' => [__('Freelancer not found.')]]);
        }

        return DB::transaction(function () use ($quest, $escrow, $releasable, $feeMinor, $netMinor, $freelancer, $actor, $reason, $releaseTrigger): PaymentEscrow {
            $idempotencyKey = 'escrow:release:'.$escrow->id.':'.$releasable;

            $walletTx = $this->wallets->credit(
                $freelancer,
                $netMinor,
                'escrow_release',
                $idempotencyKey,
                ['escrow_reference' => $escrow->reference, 'gross_minor' => $releasable],
                $escrow->id,
                $quest->id,
                0,
                null,
                $reason ?? __('Escrow released to wallet'),
                $actor,
            );

            if ($feeMinor > 0) {
                $this->wallets->recordAudit(
                    $freelancer,
                    $feeMinor,
                    'fee',
                    $idempotencyKey.':fee',
                    ['escrow_reference' => $escrow->reference],
                    $escrow->id,
                    $quest->id,
                    null,
                    __('Platform service fee'),
                );
            }

            $escrow->update([
                'status' => 'released',
                'released_minor' => (int) $escrow->released_minor + $releasable,
                'fee_minor' => (int) $escrow->fee_minor + $feeMinor,
                'released_at' => now(),
            ]);

            $quest->update([
                'paid_out_minor' => (int) $quest->paid_out_minor + $releasable,
                'escrow_status' => 'released',
            ]);

            $trigger = $releaseTrigger ?? $this->inferReleaseTrigger($actor, $reason);
            app(\App\Services\Finance\FinancialLedgerBridgeService::class)->onEscrowReleased(
                $escrow->fresh(),
                $releasable,
                $trigger,
                $walletTx->reference ?? null,
            );

            return $escrow->fresh();
        });
    }

    public function refundEscrow(Quest $quest, User $admin, string $reason, ?int $amountMinor = null): PaymentEscrow
    {
        $escrow = PaymentEscrow::query()->where('quest_id', $quest->id)->lockForUpdate()->first();
        if ($escrow === null) {
            throw ValidationException::withMessages(['escrow' => [__('No payment escrow found.')]]);
        }

        $refundable = $escrow->releasableMinor();
        $amount = $amountMinor ?? $refundable;
        if ($amount <= 0 || $amount > $refundable) {
            throw ValidationException::withMessages(['amount' => [__('Invalid refund amount.')]]);
        }

        return DB::transaction(function () use ($quest, $escrow, $amount, $admin, $reason): PaymentEscrow {
            $escrow->update([
                'status' => 'refunded',
                'refunded_minor' => (int) $escrow->refunded_minor + $amount,
                'refunded_at' => now(),
            ]);

            $quest->update([
                'refunded_minor' => (int) ($quest->refunded_minor ?? 0) + $amount,
                'escrow_status' => 'refunded',
            ]);

            AdminFinancialLedgerEntry::query()->create([
                'quest_id' => $quest->id,
                'quest_offer_id' => $quest->accepted_quest_offer_id,
                'client_id' => $quest->client_id,
                'freelancer_id' => $quest->freelancer_id,
                'admin_user_id' => $admin->id,
                'type' => 'refund',
                'direction' => 'debit',
                'source' => 'admin',
                'description' => $reason,
                'gross_amount_minor' => $amount,
                'fee_amount_minor' => 0,
                'net_amount_minor' => -$amount,
                'balance_after_minor' => max(0, $escrow->releasableMinor()),
                'admin_reason' => $reason,
                'meta' => ['payment_escrow_id' => $escrow->id],
                'occurred_at' => now(),
            ]);

            app(\App\Services\Finance\FinancialLedgerBridgeService::class)
                ->onEscrowRefunded($escrow->fresh(), $amount, $reason);

            return $escrow->fresh();
        });
    }

    protected function inferReleaseTrigger(?User $actor, ?string $reason): string
    {
        if ($actor === null && $reason !== null && str_contains(strtolower($reason), 'auto-release')) {
            return 'auto_release';
        }

        if ($actor?->role?->slug === 'super_admin' || $actor?->role?->slug === 'admin') {
            return 'manual_admin';
        }

        if ($actor !== null) {
            return 'client_marked_complete';
        }

        return 'escrow_release';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleChargeSuccess(array $payload): void
    {
        $data = $payload['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');
        if ($reference === '') {
            return;
        }

        $this->markEscrowFundedFromPaystack($reference, $data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleTransferSuccess(array $payload): void
    {
        $data = $payload['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');
        if ($reference === '') {
            return;
        }

        $withdrawal = \App\Models\WalletWithdrawal::query()
            ->where('paystack_reference', $reference)
            ->where('status', 'pending')
            ->first();

        if ($withdrawal === null) {
            return;
        }

        $withdrawal->update([
            'status' => 'completed',
            'paystack_transfer_code' => $data['transfer_code'] ?? $withdrawal->paystack_transfer_code,
            'processed_at' => now(),
        ]);

        app(\App\Services\Finance\FinancialLedgerBridgeService::class)
            ->onWithdrawalConfirmed($withdrawal->fresh());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleTransferFailed(array $payload): void
    {
        $data = $payload['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');
        if ($reference === '') {
            return;
        }

        $withdrawal = \App\Models\WalletWithdrawal::query()
            ->where('paystack_reference', $reference)
            ->first();

        if ($withdrawal === null) {
            return;
        }

        DB::transaction(function () use ($withdrawal, $data): void {
            if ($withdrawal->status === 'failed') {
                return;
            }

            $user = User::query()->find($withdrawal->user_id);
            if ($user !== null) {
                $this->wallets->credit(
                    $user,
                    (int) $withdrawal->amount_minor + (int) $withdrawal->fee_minor,
                    'withdrawal_reversal',
                    'withdrawal:reverse:'.$withdrawal->id,
                    ['withdrawal_reference' => $withdrawal->reference],
                    null,
                    null,
                    0,
                    $withdrawal->paystack_reference,
                    __('Withdrawal failed — funds returned'),
                );
            }

            $withdrawal->update([
                'status' => 'failed',
                'failure_reason' => (string) ($data['reason'] ?? __('Transfer failed.')),
                'processed_at' => now(),
            ]);

            app(\App\Services\Finance\FinancialLedgerBridgeService::class)
                ->onWithdrawalReversed($withdrawal->fresh());
        });
    }

    protected function assertFundingPreconditions(Quest $quest, QuestOffer $offer, User $client): void
    {
        if ((int) $quest->client_id !== (int) $client->id) {
            abort(403);
        }

        if ((int) $quest->accepted_quest_offer_id !== (int) $offer->id || $offer->status !== 'accepted') {
            throw ValidationException::withMessages(['offer' => [__('Funding only applies to the accepted proposal.')]]);
        }

        if ($quest->escrow_status !== 'awaiting_funding') {
            throw ValidationException::withMessages(['escrow' => [__('Escrow is not awaiting funding.')]]);
        }
    }

    protected function quotedMinor(QuestOffer $offer, Quest $quest): int
    {
        $p = $offer->pricing_snapshot ?? [];

        return (int) ($p['grand_total_minor'] ?? $offer->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0);
    }
}
