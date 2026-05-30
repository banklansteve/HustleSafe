<?php

namespace App\Services\Proposals;

use App\Enums\QuestStatus;
use App\Models\ProposalBehaviourLog;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Models\UserTrustMetric;
use App\Services\ClientTrustScoreService;
use App\Services\FreelancerTrustScoreService;
use Illuminate\Support\Facades\Schema;

class ProposalTrustBehaviourService
{
    public const SHORTLISTED_WITHDRAWAL_PENALTY = 3;

    public const CLIENT_GHOST_STRIKE_THRESHOLD = 2;

    public function recordShortlistedWithdrawal(QuestOffer $offer): void
    {
        if (! Schema::hasTable('proposal_behaviour_logs')) {
            return;
        }

        $offer->loadMissing('freelancer', 'quest');
        $freelancer = $offer->freelancer;
        if (! $freelancer) {
            return;
        }

        ProposalBehaviourLog::query()->create([
            'quest_id' => $offer->quest_id,
            'quest_offer_id' => $offer->id,
            'user_id' => $freelancer->id,
            'event_type' => 'shortlisted_withdrawal',
            'meta' => [
                'was_shortlisted' => true,
                'shortlisted_at' => $offer->shortlisted_at?->toIso8601String(),
            ],
            'occurred_at' => now(),
        ]);

        $metrics = UserTrustMetric::query()->firstOrCreate(['user_id' => $freelancer->id]);
        $metrics->increment('shortlisted_withdrawal_count');
        $metrics->increment('reliability_penalty_points', self::SHORTLISTED_WITHDRAWAL_PENALTY);
        app(FreelancerTrustScoreService::class)->sync($freelancer->fresh());
    }

    public function evaluateClientProposalGhosting(Quest $quest): void
    {
        if (! Schema::hasTable('proposal_behaviour_logs') || $quest->status !== QuestStatus::Open) {
            return;
        }

        $quest->loadMissing('client');
        $client = $quest->client;
        if (! $client) {
            return;
        }

        $submittedCount = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->where('status', 'submitted')
            ->count();

        if ($submittedCount < 3) {
            return;
        }

        $hasClientAction = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->where(function ($q): void {
                $q->where('status', 'shortlisted')
                    ->orWhereNotNull('declined_at')
                    ->orWhere('status', 'pending_award')
                    ->orWhere('status', 'accepted');
            })
            ->exists();

        if ($hasClientAction) {
            return;
        }

        $ageDays = $quest->created_at?->diffInDays(now()) ?? 0;
        if ($ageDays < 14) {
            return;
        }

        $alreadyLogged = ProposalBehaviourLog::query()
            ->where('quest_id', $quest->id)
            ->where('event_type', 'client_proposal_ghosting')
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        ProposalBehaviourLog::query()->create([
            'quest_id' => $quest->id,
            'quest_offer_id' => null,
            'user_id' => $client->id,
            'event_type' => 'client_proposal_ghosting',
            'meta' => [
                'pending_proposals' => $submittedCount,
                'quest_age_days' => $ageDays,
            ],
            'occurred_at' => now(),
        ]);

        $metrics = UserTrustMetric::query()->firstOrCreate(['user_id' => $client->id]);
        $metrics->increment('client_proposal_ghost_strikes');

        if ($metrics->fresh()->client_proposal_ghost_strikes >= self::CLIENT_GHOST_STRIKE_THRESHOLD) {
            $metrics->update(['client_quest_posting_flagged' => true]);
        }

        app(ClientTrustScoreService::class)->sync($client->fresh());
    }

    public function scanOpenQuestsForClientGhosting(): int
    {
        $count = 0;
        Quest::query()
            ->where('status', QuestStatus::Open)
            ->where('created_at', '<=', now()->subDays(14))
            ->chunkById(50, function ($quests) use (&$count): void {
                foreach ($quests as $quest) {
                    $before = ProposalBehaviourLog::query()
                        ->where('quest_id', $quest->id)
                        ->where('event_type', 'client_proposal_ghosting')
                        ->exists();
                    $this->evaluateClientProposalGhosting($quest);
                    $after = ProposalBehaviourLog::query()
                        ->where('quest_id', $quest->id)
                        ->where('event_type', 'client_proposal_ghosting')
                        ->exists();
                    if (! $before && $after) {
                        $count++;
                    }
                }
            });

        return $count;
    }
}
