<?php

namespace App\Services\Moderation;

use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Admin\ContractManagement\ContractPatrolAnomalyService;
use App\Services\Admin\QuestPatrol\QuestPatrolAnomalyService;
use App\Services\Admin\UserActivityPatrol\UserActivityPatrolAnomalyService;
use Illuminate\Support\Facades\DB;

/**
 * Runs additive automated moderation scans at creation / lifecycle events.
 * Skips detections that already exist elsewhere (e.g. off-platform contact in content moderation).
 */
final class ModerationDetectionHookService
{
    public function questCreated(Quest $quest): void
    {
        DB::afterCommit(function () use ($quest): void {
            $fresh = $quest->fresh(['client', 'questCategory']);
            if ($fresh === null) {
                return;
            }

            app(QuestPatrolAnomalyService::class)->scanQuest($fresh);

            if ($fresh->client_id) {
                $client = User::query()->find($fresh->client_id);
                if ($client !== null) {
                    app(UserActivityPatrolAnomalyService::class)->scanUser($client);
                }
            }
        });
    }

    public function proposalCreated(QuestOffer $offer): void
    {
        DB::afterCommit(function () use ($offer): void {
            $fresh = $offer->fresh(['freelancer', 'quest']);
            if ($fresh === null) {
                return;
            }

            app(QuestPatrolAnomalyService::class)->scanProposal($fresh);

            if ($fresh->freelancer_id) {
                $freelancer = User::query()->find($fresh->freelancer_id);
                if ($freelancer !== null) {
                    app(UserActivityPatrolAnomalyService::class)->scanUser($freelancer);
                }
            }
        });
    }

    public function disputeOpened(QuestDispute $dispute): void
    {
        DB::afterCommit(function () use ($dispute): void {
            $dispute->loadMissing('quest.contract', 'quest');
            $contract = $dispute->quest?->contract;
            if ($contract !== null) {
                app(ContractPatrolAnomalyService::class)->scanContract($contract);
            }
        });
    }

    public function escrowFunded(PaymentEscrow $escrow): void
    {
        DB::afterCommit(function () use ($escrow): void {
            if (! $escrow->quest_id) {
                return;
            }

            $contract = QuestContract::query()->where('quest_id', $escrow->quest_id)->first();
            if ($contract !== null) {
                app(ContractPatrolAnomalyService::class)->scanContract($contract->fresh(['quest', 'activeDispute', 'freelancer']));
            }
        });
    }

    public function deliveryApproved(Quest $quest): void
    {
        DB::afterCommit(function () use ($quest): void {
            $contract = $quest->fresh(['contract'])?->contract;
            if ($contract !== null) {
                app(ContractPatrolAnomalyService::class)->clearOverdueDeliveryFlags($contract);
            }
        });
    }
}
