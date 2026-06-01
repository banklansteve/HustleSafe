<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccount;
use App\Enums\LedgerEventType;
use App\Models\LedgerEntry;
use App\Models\LedgerJournalBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class DoubleEntryLedgerService
{
    /**
     * @param  list<array{account: LedgerAccount|string, side: 'debit'|'credit', amount_minor: int}>  $lines
     * @param  array<string, mixed>  $context
     */
    public function postBalancedBatch(
        LedgerEventType|string $eventType,
        string $idempotencyKey,
        array $lines,
        string $createdByProcess,
        array $context = [],
        ?string $description = null,
    ): LedgerJournalBatch {
        $event = $eventType instanceof LedgerEventType ? $eventType->value : $eventType;

        if ($existing = LedgerJournalBatch::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing->load('entries');
        }

        $this->assertBalanced($lines);

        return DB::transaction(function () use ($event, $idempotencyKey, $lines, $createdByProcess, $context, $description): LedgerJournalBatch {
            $batch = LedgerJournalBatch::query()->create([
                'event_type' => $event,
                'idempotency_key' => $idempotencyKey,
                'payment_escrow_id' => $context['payment_escrow_id'] ?? null,
                'quest_id' => $context['quest_id'] ?? null,
                'quest_contract_id' => $context['quest_contract_id'] ?? null,
                'wallet_withdrawal_id' => $context['wallet_withdrawal_id'] ?? null,
                'client_id' => $context['client_id'] ?? null,
                'freelancer_id' => $context['freelancer_id'] ?? null,
                'paystack_reference' => $context['paystack_reference'] ?? null,
                'description' => $description,
                'created_by_process' => $createdByProcess,
                'meta' => $context['meta'] ?? null,
                'occurred_at' => $context['occurred_at'] ?? now(),
            ]);

            foreach ($lines as $line) {
                $account = $line['account'] instanceof LedgerAccount
                    ? $line['account']->value
                    : (string) $line['account'];

                LedgerEntry::query()->create([
                    'batch_id' => $batch->id,
                    'ledger_account' => $account,
                    'side' => $line['side'],
                    'amount_minor' => (int) $line['amount_minor'],
                    'currency' => 'NGN',
                    'payment_escrow_id' => $context['payment_escrow_id'] ?? null,
                    'quest_id' => $context['quest_id'] ?? null,
                    'wallet_withdrawal_id' => $context['wallet_withdrawal_id'] ?? null,
                    'client_id' => $context['client_id'] ?? null,
                    'freelancer_id' => $context['freelancer_id'] ?? null,
                    'paystack_reference' => $context['paystack_reference'] ?? null,
                    'occurred_at' => $context['occurred_at'] ?? now(),
                ]);
            }

            return $batch->load('entries');
        });
    }

    public function reverseBatch(LedgerJournalBatch $original, string $reason, string $createdByProcess): LedgerJournalBatch
    {
        if ($original->reverses_batch_id !== null) {
            throw ValidationException::withMessages(['batch' => [__('Cannot reverse a reversal batch.')]]);
        }

        $idempotencyKey = 'reversal:'.$original->id;

        if ($existing = LedgerJournalBatch::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing->load('entries');
        }

        $lines = $original->entries->map(fn (LedgerEntry $entry) => [
            'account' => $entry->ledger_account,
            'side' => $entry->side === 'debit' ? 'credit' : 'debit',
            'amount_minor' => (int) $entry->amount_minor,
        ])->all();

        return DB::transaction(function () use ($original, $reason, $createdByProcess, $lines, $idempotencyKey): LedgerJournalBatch {
            $batch = LedgerJournalBatch::query()->create([
                'event_type' => LedgerEventType::Reversal->value,
                'idempotency_key' => $idempotencyKey,
                'payment_escrow_id' => $original->payment_escrow_id,
                'quest_id' => $original->quest_id,
                'quest_contract_id' => $original->quest_contract_id,
                'wallet_withdrawal_id' => $original->wallet_withdrawal_id,
                'client_id' => $original->client_id,
                'freelancer_id' => $original->freelancer_id,
                'paystack_reference' => $original->paystack_reference,
                'description' => __('Reversal of :ref — :reason', ['ref' => $original->reference, 'reason' => $reason]),
                'created_by_process' => $createdByProcess,
                'reverses_batch_id' => $original->id,
                'reversal_reason' => $reason,
                'occurred_at' => now(),
            ]);

            foreach ($lines as $line) {
                LedgerEntry::query()->create([
                    'batch_id' => $batch->id,
                    'ledger_account' => $line['account'],
                    'side' => $line['side'],
                    'amount_minor' => $line['amount_minor'],
                    'currency' => 'NGN',
                    'payment_escrow_id' => $original->payment_escrow_id,
                    'quest_id' => $original->quest_id,
                    'wallet_withdrawal_id' => $original->wallet_withdrawal_id,
                    'client_id' => $original->client_id,
                    'freelancer_id' => $original->freelancer_id,
                    'paystack_reference' => $original->paystack_reference,
                    'occurred_at' => now(),
                ]);
            }

            return $batch->load('entries');
        });
    }

    /**
     * @return array{debits_minor: int, credits_minor: int, balanced: bool, variance_minor: int}
     */
    public function globalBalanceCheck(): array
    {
        $debits = (int) LedgerEntry::query()->where('side', 'debit')->sum('amount_minor');
        $credits = (int) LedgerEntry::query()->where('side', 'credit')->sum('amount_minor');

        return [
            'debits_minor' => $debits,
            'credits_minor' => $credits,
            'balanced' => $debits === $credits,
            'variance_minor' => abs($debits - $credits),
        ];
    }

    public function accountBalanceMinor(LedgerAccount $account): int
    {
        $debits = (int) LedgerEntry::query()
            ->where('ledger_account', $account->value)
            ->where('side', 'debit')
            ->sum('amount_minor');

        $credits = (int) LedgerEntry::query()
            ->where('ledger_account', $account->value)
            ->where('side', 'credit')
            ->sum('amount_minor');

        return $credits - $debits;
    }

    /**
     * @param  list<array{account: LedgerAccount|string, side: 'debit'|'credit', amount_minor: int}>  $lines
     */
    private function assertBalanced(array $lines): void
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Ledger batch requires at least one line.');
        }

        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            $amount = (int) ($line['amount_minor'] ?? 0);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Ledger line amounts must be positive.');
            }

            match ($line['side']) {
                'debit' => $debits += $amount,
                'credit' => $credits += $amount,
                default => throw new InvalidArgumentException('Invalid ledger side.'),
            };
        }

        if ($debits !== $credits) {
            throw new InvalidArgumentException("Unbalanced ledger batch: debits {$debits} ≠ credits {$credits}.");
        }
    }
}
