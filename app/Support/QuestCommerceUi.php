<?php

namespace App\Support;

use App\Enums\QuestDisputeStatus;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Disputes\DisputePartyPresenter;
use App\Services\Disputes\QuestDisputeWorkflowService;
use App\Services\Quest\DisputePreventionPromptService;
use App\Services\Quest\EscrowTransparencyTimelineService;
use Illuminate\Validation\ValidationException;

final class QuestCommerceUi
{
    /**
     * @return array<string, mixed>
     */
    public static function fundingForOffer(Quest $quest, QuestOffer $offer, ?User $viewer): array
    {
        $isClient = $viewer && (int) $viewer->id === (int) $quest->client_id;
        $isAccepted = $offer->status === 'accepted'
            && (int) ($quest->accepted_quest_offer_id ?? 0) === (int) $offer->id;
        $awaiting = $quest->escrow_status === 'awaiting_funding';
        $mutualAward = $offer->isAwardMutuallyConfirmed();

        return [
            'show_fund_button' => (bool) ($isClient && $isAccepted && $awaiting && $mutualAward),
            'funding_post_url' => ($isClient && $isAccepted && $awaiting && $mutualAward)
                ? route('quests.proposals.funding-intent.store', [$quest->getRouteKey(), $offer])
                : null,
            'award_awaiting_freelancer' => $offer->status === 'pending_award',
            'award_mutually_confirmed' => $mutualAward,
            'can_cancel_award' => (bool) ($isClient && app(\App\Services\Proposals\ProposalAwardCancellationService::class)->canCancel($quest, $offer)),
            'cancel_award_url' => ($isClient && app(\App\Services\Proposals\ProposalAwardCancellationService::class)->canCancel($quest, $offer))
                ? route('quests.proposals.cancel-award', [$quest->getRouteKey(), $offer])
                : null,
            'completion' => EscrowReleasePolicy::uiPayload($quest, $viewer),
            'delivery_lifecycle' => app(\App\Services\Quest\QuestDeliveryLifecycleService::class)->uiPayload($quest, $viewer),
        ];
    }

    /**
     * @return array{can_open_dispute: bool, dispute_create_url: ?string, active_dispute: ?array{uuid: string, url: string, status: string}, dispute_block_reason: ?string}
     */
    public static function disputeForQuest(Quest $quest, ?User $viewer): array
    {
        $base = [
            'can_open_dispute' => false,
            'dispute_create_url' => null,
            'active_dispute' => null,
            'dispute_block_reason' => null,
        ];

        if ($viewer === null || ! $quest->isParty($viewer)) {
            return $base;
        }

        $offer = $quest->acceptedOffer;

        $active = QuestDispute::query()
            ->where('quest_id', $quest->id)
            ->when($offer !== null, fn ($q) => $q->where('quest_offer_id', $offer->id))
            ->orderByDesc('id')
            ->get()
            ->first(fn (QuestDispute $dispute): bool => $dispute->isActiveOnContract());

        if ($active !== null) {
            return [
                'can_open_dispute' => false,
                'dispute_create_url' => null,
                'active_dispute' => [
                    'uuid' => $active->uuid,
                    'url' => route('disputes.show', $active),
                    'status' => $active->status->value,
                    'status_label' => app(DisputePartyPresenter::class)->statusLabel($active),
                ],
                'dispute_block_reason' => null,
            ];
        }

        try {
            app(QuestDisputeWorkflowService::class)->assertCanOpen($viewer, $quest);

            return [
                'can_open_dispute' => true,
                'dispute_create_url' => route('quests.disputes.create', $quest->getRouteKey()),
                'active_dispute' => null,
                'dispute_block_reason' => null,
            ];
        } catch (ValidationException $e) {
            return [
                'can_open_dispute' => false,
                'dispute_create_url' => null,
                'active_dispute' => null,
                'dispute_block_reason' => (string) collect($e->errors())->flatten()->first(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function partyExtras(Quest $quest, ?User $viewer): array
    {
        if ($viewer === null || ! $quest->isParty($viewer)) {
            return [];
        }

        return array_merge(
            self::contractForQuest($quest, $viewer),
            [
                'escrow_timeline' => app(EscrowTransparencyTimelineService::class)->build($quest),
                'dispute_prevention_prompts' => app(DisputePreventionPromptService::class)->promptsFor($quest, $viewer),
            ],
        );
    }

    /**
     * @return array{contract_url: ?string, contract_reference: ?string, contract_status: ?string, contract_status_label: ?string}
     */
    public static function contractForQuest(Quest $quest, ?User $viewer): array
    {
        $empty = [
            'contract_url' => null,
            'contract_reference' => null,
            'contract_status' => null,
            'contract_status_label' => null,
        ];

        if ($viewer === null || ! $quest->isParty($viewer)) {
            return $empty;
        }

        $contract = QuestContract::query()
            ->where('quest_id', $quest->id)
            ->when(
                $quest->accepted_quest_offer_id,
                fn ($query) => $query->where('quest_offer_id', $quest->accepted_quest_offer_id)
            )
            ->latest('id')
            ->first();

        if ($contract === null) {
            return $empty;
        }

        return [
            'contract_url' => route('contracts.show', $contract->reference_code),
            'contract_reference' => $contract->reference_code,
            'contract_status' => $contract->status->value,
            'contract_status_label' => $contract->status->label(),
        ];
    }
}
