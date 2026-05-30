<?php

namespace App\Support;

use App\Enums\QuestDisputeStatus;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\User;
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
                ? route('quests.proposals.funding-intent.store', [$quest->getRouteKey(), $offer->id])
                : null,
            'award_awaiting_freelancer' => $offer->status === 'pending_award',
            'award_mutually_confirmed' => $mutualAward,
            'completion' => EscrowReleasePolicy::uiPayload($quest, $viewer),
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

        $active = QuestDispute::query()
            ->where('quest_id', $quest->id)
            ->whereNotIn('status', [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn])
            ->first();

        if ($active !== null) {
            return [
                'can_open_dispute' => false,
                'dispute_create_url' => null,
                'active_dispute' => [
                    'uuid' => $active->uuid,
                    'url' => route('disputes.show', $active),
                    'status' => $active->status->value,
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

        return [
            'escrow_timeline' => app(EscrowTransparencyTimelineService::class)->build($quest),
            'dispute_prevention_prompts' => app(DisputePreventionPromptService::class)->promptsFor($quest, $viewer),
        ];
    }
}
