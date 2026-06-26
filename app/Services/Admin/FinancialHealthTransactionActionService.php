<?php

namespace App\Services\Admin;

use App\Enums\FinancialEscrowRecordStatus;
use App\Enums\ReconciliationExceptionStatus;
use App\Enums\ReconciliationExceptionType;
use App\Models\FinancialEscrowRecord;
use App\Models\FinancialReconciliationException;
use App\Models\Quest;
use App\Models\User;
use App\Services\QuestCompletionEventLogger;
use Illuminate\Validation\ValidationException;

final class FinancialHealthTransactionActionService
{
    public function __construct(
        private readonly QuestCompletionEventLogger $events,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function addNote(FinancialEscrowRecord $record, User $admin, string $note): array
    {
        $meta = $record->meta ?? [];
        $notes = $meta['admin_notes'] ?? [];
        $notes[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'body' => $note,
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'created_at' => now()->toIso8601String(),
        ];
        $meta['admin_notes'] = $notes;
        $record->update(['meta' => $meta]);

        $quest = $record->quest ?? Quest::query()->find($record->quest_id);
        if ($quest !== null) {
            $this->events->record($quest, 'financial_health_note', $admin, request(), [
                'escrow_record_id' => $record->id,
                'note' => $note,
            ]);
        }

        return ['ok' => true, 'notes_count' => count($notes)];
    }

    /**
     * @return array<string, mixed>
     */
    public function hold(FinancialEscrowRecord $record, User $admin, string $reason, ?string $holdUntil = null): array
    {
        $quest = $this->resolveQuest($record);

        if ($quest->release_hold_reason) {
            throw ValidationException::withMessages([
                'reason' => __('Payment is already on hold.'),
            ]);
        }

        $quest->update([
            'release_hold_reason' => $reason,
            'release_hold_by' => $admin->id,
            'release_hold_until' => $holdUntil ? \Carbon\Carbon::parse($holdUntil) : now()->addDays(7),
        ]);

        $this->events->record($quest->fresh(), 'release_hold', $admin, request(), [
            'source' => 'financial_health_dashboard',
            'escrow_record_id' => $record->id,
        ]);

        return ['ok' => true, 'on_hold' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function liftHold(FinancialEscrowRecord $record, User $admin, string $reason): array
    {
        $quest = $this->resolveQuest($record);

        if (! $quest->release_hold_reason) {
            throw ValidationException::withMessages([
                'reason' => __('No active hold on this payment.'),
            ]);
        }

        $quest->update([
            'release_hold_reason' => null,
            'release_hold_by' => null,
            'release_hold_until' => null,
        ]);

        $this->events->record($quest->fresh(), 'release_hold_lifted', $admin, request(), [
            'source' => 'financial_health_dashboard',
            'reason' => $reason,
            'escrow_record_id' => $record->id,
        ]);

        return ['ok' => true, 'on_hold' => false];
    }

    /**
     * @return array<string, mixed>
     */
    public function investigate(FinancialEscrowRecord $record, User $admin, string $reason): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('financial_reconciliation_exceptions')) {
            throw ValidationException::withMessages([
                'reason' => __('Investigation workflow is not available.'),
            ]);
        }

        $exception = FinancialReconciliationException::query()->create([
            'type' => ReconciliationExceptionType::UnconfirmedEscrowFunding->value,
            'status' => ReconciliationExceptionStatus::UnderInvestigation->value,
            'assigned_to_user_id' => $admin->id,
            'payment_escrow_id' => $record->payment_escrow_id,
            'paystack_reference' => $record->paystack_reference,
            'variance_minor' => (int) $record->total_funded_minor,
            'title' => __('Financial health review: :ref', ['ref' => $record->contract_reference ?: $record->escrow_reference]),
            'description' => $reason,
            'investigation_notes' => $reason,
            'meta' => [
                'source' => 'financial_health_dashboard',
                'escrow_record_id' => $record->id,
                'opened_by' => $admin->id,
            ],
        ]);

        $meta = $record->meta ?? [];
        $meta['last_investigation_exception_id'] = $exception->id;
        $record->update(['meta' => $meta]);

        return [
            'ok' => true,
            'exception_id' => $exception->id,
            'exception_url' => route('admin.financial-audit.exceptions.index'),
        ];
    }

    private function resolveQuest(FinancialEscrowRecord $record): Quest
    {
        $quest = $record->quest;
        if ($quest === null) {
            throw ValidationException::withMessages([
                'record' => __('Linked quest not found for this escrow record.'),
            ]);
        }

        if ($record->status !== FinancialEscrowRecordStatus::Held->value) {
            throw ValidationException::withMessages([
                'record' => __('Hold actions apply only to held escrow payments.'),
            ]);
        }

        return $quest;
    }
}
