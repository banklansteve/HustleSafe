<?php

namespace App\Services\Admin\ContractManagement;

use App\Enums\ContractStatus;
use App\Enums\QuestStatus;
use App\Models\QuestContract;
use App\Models\User;
use App\Services\Contracts\ContractEventLogger;
use App\Services\Contracts\ContractLifecycleService;
use App\Services\Finance\FinancialReconciliationService;
use App\Services\Payments\EscrowPaymentService;
use App\Services\Quest\QuestDeliveryLifecycleService;
use App\Services\QuestCompletionEventLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ContractManagementActionService
{
    public function __construct(
        private readonly ContractEventLogger $events,
        private readonly EscrowPaymentService $escrow,
        private readonly QuestCompletionEventLogger $questEvents,
        private readonly QuestDeliveryLifecycleService $delivery,
        private readonly ContractLifecycleService $contractLifecycle,
        private readonly FinancialReconciliationService $reconciliation,
    ) {}

    public function assign(QuestContract $contract, User $staff, Request $request): void
    {
        $this->events->log($contract, 'contract.staff_assigned', $staff, [
            'assigned_to' => $staff->id,
            'assigned_to_name' => $staff->name,
        ], $request);
    }

    public function addNote(QuestContract $contract, User $staff, string $body, Request $request): void
    {
        $this->events->log($contract, 'contract.staff_note', $staff, [
            'body' => $body,
        ], $request);
    }

    public function flagForReview(QuestContract $contract, User $staff, string $reason, Request $request): void
    {
        $contract->update([
            'flagged_for_review' => true,
            'flagged_for_review_reason' => $reason,
            'flagged_for_review_by' => $staff->id,
            'flagged_for_review_at' => now(),
        ]);

        $this->events->log($contract, 'contract.flagged_for_review', $staff, [
            'reason' => $reason,
        ], $request);
    }

    public function qualityReview(QuestContract $contract, User $staff, int $rating, string $notes, Request $request): void
    {
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages(['rating' => __('Rating must be between 1 and 5.')]);
        }

        $this->events->log($contract, 'contract.staff_quality_review', $staff, [
            'rating' => $rating,
            'notes' => $notes,
        ], $request);
    }

    public function acknowledgeAlert(QuestContract $contract, User $staff, string $alertType, Request $request): void
    {
        $this->events->log($contract, 'contract.alert_acknowledged', $staff, [
            'alert_type' => $alertType,
        ], $request);
    }

    public function holdEscrow(QuestContract $contract, User $staff, string $reason, Request $request): void
    {
        $quest = $contract->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['contract' => __('Quest not found for this contract.')]);
        }

        $quest->forceFill([
            'release_hold_until' => now()->addDays(7),
            'release_hold_reason' => $reason,
            'release_hold_by' => $staff->id,
        ])->save();

        $this->events->log($contract, 'contract.escrow_held', $staff, [
            'reason' => $reason,
        ], $request);
    }

    public function liftEscrowHold(QuestContract $contract, User $staff, string $reason, Request $request): void
    {
        $quest = $contract->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['contract' => __('Quest not found for this contract.')]);
        }

        $quest->forceFill([
            'release_hold_until' => null,
            'release_hold_reason' => null,
            'release_hold_by' => null,
        ])->save();

        $this->questEvents->record($quest->fresh(), 'release_hold_lifted', $staff, $request, ['reason' => $reason]);
        $this->events->log($contract, 'contract.escrow_hold_lifted', $staff, ['reason' => $reason], $request);
    }

    public function terminate(QuestContract $contract, User $staff, string $reason, Request $request): void
    {
        $contract->forceFill([
            'status' => ContractStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        $this->events->log($contract, 'contract.terminated', $staff, [
            'reason' => $reason,
        ], $request);
    }

    public function forceApproveDelivery(QuestContract $contract, User $staff, string $reason, Request $request): void
    {
        $quest = $contract->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['contract' => __('Quest not found.')]);
        }

        if ($quest->delivered_at === null && $quest->freelancer_id !== null) {
            $freelancer = $quest->freelancer ?? User::query()->find($quest->freelancer_id);
            if ($freelancer !== null) {
                $this->delivery->submitDeliverable($quest, $freelancer, [
                    'summary' => __('Staff recorded delivery on behalf of parties. Reason: :reason', ['reason' => $reason]),
                    'delivery_url' => null,
                ]);
                $quest->refresh();
            }
        }

        if ($quest->delivery_acknowledged_at === null) {
            $quest->update([
                'delivery_acknowledged_at' => now(),
                'delivery_acknowledged_by' => $staff->id,
            ]);
            $this->questEvents->record($quest->fresh(), 'sa_delivery_approved', $staff, $request, ['reason' => $reason]);
            $quest->refresh();
        }

        $this->events->log($contract, 'contract.delivery_force_approved', $staff, ['reason' => $reason], $request);
    }

    public function forceRejectDelivery(QuestContract $contract, User $staff, string $note, Request $request): void
    {
        $quest = $contract->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['contract' => __('Quest not found.')]);
        }

        if ($quest->latest_delivery_submission_id === null) {
            throw ValidationException::withMessages(['delivery' => __('No delivery submission to reject.')]);
        }

        $quest->update([
            'delivery_revision_requested_at' => now(),
            'delivery_revision_requested_by' => $staff->id,
            'delivery_revision_note' => trim($note),
            'delivered_at' => null,
            'delivery_review_deadline_at' => null,
        ]);

        $this->questEvents->record($quest->fresh(), 'sa_delivery_rejected', $staff, $request, ['note' => $note]);
        $this->events->log($contract, 'contract.delivery_force_rejected', $staff, ['note' => $note], $request);
    }

    public function releasePayment(QuestContract $contract, User $staff, string $reason, Request $request, ?int $amountMinor = null): void
    {
        $quest = $contract->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['contract' => __('Quest not found.')]);
        }

        if (! in_array($quest->escrow_status, ['funded', 'partially_released'], true)) {
            throw ValidationException::withMessages(['escrow' => __('Escrow is not in a releasable state.')]);
        }

        $this->escrow->releaseEscrowToWallet(
            $quest,
            $staff,
            $reason,
            ignorePolicy: true,
            amountMinor: $amountMinor,
        );

        $quest->refresh();
        if ($amountMinor === null && $quest->escrow_status === 'released') {
            $quest->update([
                'status' => QuestStatus::Completed,
                'completed_at' => now(),
                'funds_released_at' => now(),
            ]);
            $this->contractLifecycle->markCompleted($contract->fresh(), $staff, $request);
        }

        $this->events->log($contract, 'contract.payment_released', $staff, [
            'reason' => $reason,
            'amount_minor' => $amountMinor,
        ], $request);
    }

    public function reconcileEscrow(User $staff, Request $request): array
    {
        $run = $this->reconciliation->run();

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'exceptions_found' => $run->exceptions_found,
            'message' => __('Escrow reconciliation completed.'),
        ];
    }

    /**
     * @param  list<string>  $referenceCodes
     * @return array{processed: int, failed: list<array{reference: string, error: string}>}
     */
    public function bulkReleasePayments(array $referenceCodes, User $staff, string $reason, Request $request): array
    {
        $processed = 0;
        $failed = [];

        foreach ($referenceCodes as $reference) {
            $contract = QuestContract::query()->where('reference_code', $reference)->first();
            if ($contract === null) {
                $failed[] = ['reference' => $reference, 'error' => 'Contract not found'];

                continue;
            }

            try {
                $this->forceApproveDelivery($contract, $staff, $reason, $request);
                $this->releasePayment($contract->fresh(), $staff, $reason, $request);
                $processed++;
            } catch (\Throwable $e) {
                $failed[] = ['reference' => $reference, 'error' => $e->getMessage()];
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * @param  list<string>  $referenceCodes
     * @return array{processed: int, failed: list<array{reference: string, error: string}>}
     */
    public function bulkHoldEscrow(array $referenceCodes, User $staff, string $reason, Request $request): array
    {
        $processed = 0;
        $failed = [];

        foreach ($referenceCodes as $reference) {
            $contract = QuestContract::query()->where('reference_code', $reference)->first();
            if ($contract === null) {
                $failed[] = ['reference' => $reference, 'error' => 'Contract not found'];

                continue;
            }

            try {
                $this->holdEscrow($contract, $staff, $reason, $request);
                $processed++;
            } catch (\Throwable $e) {
                $failed[] = ['reference' => $reference, 'error' => $e->getMessage()];
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }
}
