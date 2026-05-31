<?php

namespace App\Services\Contracts;

use App\Enums\ContractStatus;
use App\Enums\QuestStatus;
use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestDispute;
use App\Models\User;
use App\Notifications\ContractActivatedNotification;
use App\Notifications\ContractCancelledNotification;
use App\Notifications\ContractCompletedNotification;
use App\Notifications\ContractDisputedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractLifecycleService
{
    public function __construct(private readonly ContractEventLogger $events) {}

    public function activateFromEscrowFunding(Quest $quest, PaymentEscrow $escrow, ?Request $request = null): void
    {
        $contract = QuestContract::query()
            ->where('quest_id', $quest->id)
            ->where('quest_offer_id', $quest->accepted_quest_offer_id)
            ->first();

        if ($contract === null || $contract->status !== ContractStatus::PendingEscrow) {
            return;
        }

        $contract->update([
            'status' => ContractStatus::Active,
            'activated_at' => now(),
            'escrow_funded_at' => $escrow->funded_at ?? now(),
            'escrow_funding_reference' => $escrow->reference,
            'contract_start_date' => now('Africa/Lagos')->toDateString(),
        ]);

        $this->events->log($contract, 'contract.activated', null, [
            'escrow_reference' => $escrow->reference,
        ], $request);

        $contract->client?->notify(new ContractActivatedNotification($contract));
        $contract->freelancer?->notify(new ContractActivatedNotification($contract));
    }

    public function markCompleted(QuestContract $contract, ?User $actor = null, ?Request $request = null): void
    {
        if (in_array($contract->status, [ContractStatus::Completed, ContractStatus::Cancelled], true)) {
            return;
        }

        $contract->update([
            'status' => ContractStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->events->log($contract, 'contract.completed', $actor, [], $request);
        $contract->client?->notify(new ContractCompletedNotification($contract));
        $contract->freelancer?->notify(new ContractCompletedNotification($contract));
    }

    public function markDisputed(QuestContract $contract, QuestDispute $dispute, ?User $actor = null, ?Request $request = null): void
    {
        if ($contract->status === ContractStatus::Cancelled) {
            return;
        }

        $contract->update([
            'status' => ContractStatus::Disputed,
            'active_dispute_id' => $dispute->id,
        ]);

        $this->events->log($contract, 'contract.disputed', $actor, [
            'dispute_id' => $dispute->id,
        ], $request);

        $contract->client?->notify(new ContractDisputedNotification($contract, $dispute));
        $contract->freelancer?->notify(new ContractDisputedNotification($contract, $dispute));
    }

    public function resolveDispute(QuestContract $contract, ?Request $request = null): void
    {
        if ($contract->status !== ContractStatus::Disputed) {
            return;
        }

        $contract->update([
            'status' => ContractStatus::Active,
            'active_dispute_id' => null,
        ]);

        $this->events->log($contract, 'contract.dispute_resolved', null, [], $request);
    }

    public function cancelExpiredPendingEscrow(QuestContract $contract, string $reason): void
    {
        if ($contract->status !== ContractStatus::PendingEscrow) {
            return;
        }

        DB::transaction(function () use ($contract, $reason): void {
            $contract->update([
                'status' => ContractStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $quest = $contract->quest;
            if ($quest !== null && $quest->escrow_status === 'awaiting_funding') {
                $quest->update([
                    'status' => QuestStatus::Assigned,
                    'escrow_status' => 'awaiting_funding',
                ]);
            }

            $this->events->log($contract, 'contract.cancelled', null, ['reason' => $reason]);
            $contract->client?->notify(new ContractCancelledNotification($contract, $reason));
            $contract->freelancer?->notify(new ContractCancelledNotification($contract, $reason));
        });
    }

    public function setAmendmentPending(QuestContract $contract): void
    {
        if ($contract->status === ContractStatus::Active) {
            $contract->update(['status' => ContractStatus::AmendmentPending]);
        }
    }

    public function clearAmendmentPending(QuestContract $contract): void
    {
        if ($contract->status === ContractStatus::AmendmentPending) {
            $contract->update(['status' => ContractStatus::Active]);
        }
    }
}
