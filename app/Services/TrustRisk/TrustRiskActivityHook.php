<?php

namespace App\Services\TrustRisk;

use App\Models\Quest;
use App\Models\QuestConversationThread;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Operations\StaffTrustWatchlistService;
use App\Support\TrustRisk\UserRiskScoreDispatcher;

class TrustRiskActivityHook
{
    public function __construct(private readonly StaffTrustWatchlistService $watchlist) {}

    public function userTouched(?int $userId): void
    {
        if ($userId) {
            UserRiskScoreDispatcher::dispatch($userId);
        }
    }

    public function questPosted(Quest $quest): void
    {
        $this->userTouched($quest->client_id);
        $client = User::query()->find($quest->client_id);
        if ($client) {
            $this->watchlist->recordActivity(
                $client,
                'quest_posted',
                'New Quest posted',
                $quest->title,
                Quest::class,
                $quest->id,
                route('operations.moderation.index', ['module' => 'quests', 'q' => $quest->reference_code ?? $quest->id]),
                'observe',
            );
        }
    }

    public function proposalSubmitted(QuestOffer $offer): void
    {
        $this->userTouched($offer->freelancer_id);
        $freelancer = User::query()->find($offer->freelancer_id);
        if ($freelancer) {
            $this->watchlist->recordActivity(
                $freelancer,
                'proposal_submitted',
                'New proposal submitted',
                'Proposal #'.$offer->id,
                QuestOffer::class,
                $offer->id,
                route('operations.moderation.index', ['module' => 'proposals', 'q' => (string) $offer->id]),
                'observe',
            );
        }
    }

    public function contractInitiated(int $clientId, int $freelancerId, ?int $escrowId = null): void
    {
        UserRiskScoreDispatcher::dispatchMany([$clientId, $freelancerId]);

        foreach ([$clientId, $freelancerId] as $uid) {
            $user = User::query()->find($uid);
            if ($user) {
                $this->watchlist->recordActivity(
                    $user,
                    'contract_initiated',
                    'New contract initiated',
                    $escrowId ? "Escrow #{$escrowId}" : null,
                    null,
                    $escrowId,
                    route('operations.payment-monitoring.index'),
                    'concern',
                );
            }
        }
    }

    public function disputeOpened(QuestDispute $dispute): void
    {
        $dispute->loadMissing('quest:id,client_id', 'offer:id,freelancer_id');
        UserRiskScoreDispatcher::dispatchMany([
            (int) $dispute->quest?->client_id,
            (int) $dispute->offer?->freelancer_id,
            (int) $dispute->opened_by_user_id,
        ]);
    }

    public function conversationStarted(QuestConversationThread $thread): void
    {
        $thread->loadMissing('quest:id,client_id');
        $participants = array_filter([
            (int) $thread->quest?->client_id,
            (int) $thread->freelancer_id,
        ]);

        foreach ($participants as $uid) {
            $user = User::query()->find($uid);
            if ($user) {
                $this->watchlist->recordActivity(
                    $user,
                    'conversation_started',
                    'New conversation started',
                    null,
                    QuestConversationThread::class,
                    $thread->id,
                    route('operations.communications-log.index'),
                    'observe',
                );
            }
        }
    }
}
