<?php

namespace App\Services\Contracts;

use App\Enums\ContractStatus;
use App\Enums\QuestStatus;
use App\Models\FinancialEscrowRecord;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ensures every awarded quest engagement has a quest_contracts registry row.
 *
 * A contract row is normally created when the freelancer confirms an award
 * (FinalizeProposalAwardJob → ContractGenerationService). Legacy or queued
 * awards may have funded escrow without a contract record — this service
 * backfills those gaps without re-sending party notifications.
 */
final class ContractRegistrySyncService
{
    public function __construct(
        private readonly ContractGenerationService $generation,
        private readonly ContractLifecycleService $lifecycle,
        private readonly ContractEventLogger $events,
    ) {}

    /**
     * @return array{created: int, reconciled: int, skipped: int}
     */
    public function syncMissing(): array
    {
        $created = 0;
        $reconciled = 0;
        $skipped = 0;

        $quests = Quest::query()
            ->where(function ($query): void {
                $query->whereNotNull('accepted_quest_offer_id')
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('freelancer_id')
                            ->whereHas('offers', fn ($offer) => $offer->where('status', 'accepted'));
                    });
            })
            ->whereDoesntHave('contract')
            ->with(['acceptedOffer', 'paymentEscrow', 'client', 'freelancer'])
            ->orderBy('id')
            ->get();

        foreach ($quests as $quest) {
            $offer = $this->resolveAcceptedOffer($quest);
            if ($offer === null) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($quest, $offer, &$created, &$reconciled): void {
                    $contract = $this->generation->generateFromAward($quest, $offer, null, silent: true);
                    $created++;
                    $this->reconcileContractWithQuest($quest->fresh(['paymentEscrow']), $contract);
                    $reconciled++;
                });
            } catch (\Throwable $e) {
                $skipped++;
                Log::warning('ContractRegistrySyncService: failed to backfill contract', [
                    'quest_id' => $quest->id,
                    'offer_id' => $offer->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->reconcileExistingContracts();

        return compact('created', 'reconciled', 'skipped');
    }

    public function hasMissingContracts(): bool
    {
        return Quest::query()
            ->where(function ($query): void {
                $query->whereNotNull('accepted_quest_offer_id')
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('freelancer_id')
                            ->whereHas('offers', fn ($offer) => $offer->where('status', 'accepted'));
                    });
            })
            ->whereDoesntHave('contract')
            ->exists();
    }

    private function resolveAcceptedOffer(Quest $quest): ?QuestOffer
    {
        if ($quest->acceptedOffer !== null) {
            return $quest->acceptedOffer;
        }

        return QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->where('status', 'accepted')
            ->latest('id')
            ->first();
    }

    private function reconcileContractWithQuest(Quest $quest, QuestContract $contract): void
    {
        $contract->refresh();
        $quest->loadMissing('paymentEscrow');

        if ($contract->status === ContractStatus::PendingEscrow
            && in_array((string) $quest->escrow_status, ['funded', 'partially_released', 'released'], true)) {
            if ($quest->paymentEscrow !== null) {
                $this->lifecycle->activateFromEscrowFunding($quest, $quest->paymentEscrow);
            } else {
                $contract->update([
                    'status' => ContractStatus::Active,
                    'activated_at' => $quest->escrow_funded_at ?? now(),
                    'escrow_funded_at' => $quest->escrow_funded_at ?? now(),
                ]);
                $this->events->log($contract, 'contract.activated', null, [
                    'source' => 'registry_sync',
                ]);
            }
        }

        $contract->refresh();

        if ($quest->status === QuestStatus::InDispute || $quest->dispute_opened) {
            $dispute = QuestDispute::query()
                ->where('quest_id', $quest->id)
                ->whereNull('resolved_at')
                ->latest('id')
                ->first();

            $contract->update([
                'status' => ContractStatus::Disputed,
                'active_dispute_id' => $dispute?->id ?? $contract->active_dispute_id,
            ]);
        } elseif (in_array($quest->status, [QuestStatus::Completed, QuestStatus::Closed], true)
            || $quest->escrow_status === 'released') {
            $contract->update([
                'status' => ContractStatus::Completed,
                'completed_at' => $quest->completed_at ?? $quest->funds_released_at ?? now(),
            ]);
        } elseif (in_array($quest->status, [
            QuestStatus::CancelledMutual,
            QuestStatus::CancelledByAdmin,
            QuestStatus::WithdrawnByClient,
            QuestStatus::WithdrawnByFreelancer,
        ], true)) {
            $contract->update([
                'status' => ContractStatus::Cancelled,
                'cancelled_at' => $quest->updated_at ?? now(),
            ]);
        }

        FinancialEscrowRecord::query()
            ->where('quest_id', $quest->id)
            ->where(function ($query) use ($contract): void {
                $query->whereNull('quest_contract_id')
                    ->orWhere('contract_reference', '!=', $contract->reference_code);
            })
            ->update([
                'quest_contract_id' => $contract->id,
                'contract_reference' => $contract->reference_code,
            ]);
    }

    private function reconcileExistingContracts(): void
    {
        QuestContract::query()
            ->with(['quest.paymentEscrow'])
            ->chunkById(100, function ($contracts): void {
                foreach ($contracts as $contract) {
                    $quest = $contract->quest;
                    if ($quest === null) {
                        continue;
                    }

                    if ($contract->status === ContractStatus::PendingEscrow
                        && in_array((string) $quest->escrow_status, ['funded', 'partially_released'], true)
                        && $quest->paymentEscrow !== null) {
                        $this->lifecycle->activateFromEscrowFunding($quest, $quest->paymentEscrow);
                    }

                    FinancialEscrowRecord::query()
                        ->where('quest_id', $contract->quest_id)
                        ->whereNull('quest_contract_id')
                        ->update([
                            'quest_contract_id' => $contract->id,
                            'contract_reference' => $contract->reference_code,
                        ]);
                }
            });
    }
}
