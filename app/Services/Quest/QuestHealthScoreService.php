<?php

namespace App\Services\Quest;

use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestConversationMessage;
use App\Models\QuestOffer;
use App\Services\QuestEngagementLifecycleService;
use Carbon\Carbon;

class QuestHealthScoreService
{
    public const LOW_HEALTH_THRESHOLD = 50;

    public function __construct(
        private readonly QuestEngagementLifecycleService $lifecycle,
    ) {}

    public function score(Quest $quest): int
    {
        if (! $quest->accepted_quest_offer_id) {
            return 100;
        }

        $score = 100;
        $quest->loadMissing(['client', 'acceptedOffer', 'disputes']);

        $score -= $this->responsivenessPenalty($quest);
        $score -= $this->inactivityPenalty($quest);
        $score -= $this->escrowPenalty($quest);
        $score -= $this->milestonePenalty($quest);

        return max(0, min(100, $score));
    }

    public function refresh(Quest $quest): int
    {
        $score = $this->score($quest);
        $quest->forceFill([
            'health_score' => $score,
            'health_score_updated_at' => now(),
        ])->saveQuietly();

        return $score;
    }

    public function refreshActiveEngagements(): int
    {
        $count = 0;
        Quest::query()
            ->whereNotNull('accepted_quest_offer_id')
            ->whereIn('status', [
                QuestStatus::Assigned,
                QuestStatus::InProgress,
                QuestStatus::Paused,
                QuestStatus::PendingReview,
            ])
            ->chunkById(100, function ($quests) use (&$count): void {
                foreach ($quests as $quest) {
                    $this->refresh($quest);
                    $count++;
                }
            });

        return $count;
    }

    private function responsivenessPenalty(Quest $quest): int
    {
        $client = $quest->client;
        if (! $client) {
            return 0;
        }

        $pendingOffers = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->whereNull('declined_at')
            ->whereNull('withdrawn_at')
            ->whereNull('accepted_at')
            ->count();

        if ($pendingOffers === 0 && $quest->status === QuestStatus::Open) {
            return 0;
        }

        $lastClientMessage = QuestConversationMessage::query()
            ->whereHas('thread', fn ($q) => $q->where('quest_id', $quest->id))
            ->where('user_id', $client->id)
            ->max('created_at');

        $lastOfferAt = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->max('created_at');

        $anchor = collect([$lastOfferAt, $quest->acceptedOffer?->accepted_at])
            ->filter()
            ->map(fn ($d) => Carbon::parse($d))
            ->sortDesc()
            ->first();

        if (! $anchor) {
            return 0;
        }

        if ($lastClientMessage && Carbon::parse($lastClientMessage)->greaterThan($anchor)) {
            return 0;
        }

        $days = $anchor->diffInDays(now());

        return match (true) {
            $days >= 10 => 30,
            $days >= 5 => 20,
            $days >= 3 => 10,
            default => 0,
        };
    }

    private function inactivityPenalty(Quest $quest): int
    {
        $lastActivity = $this->lastActivityAt($quest);
        if (! $lastActivity) {
            return 0;
        }

        $days = $lastActivity->diffInDays(now());

        return match (true) {
            $days >= 14 => 25,
            $days >= 7 => 15,
            $days >= 4 => 8,
            default => 0,
        };
    }

    private function escrowPenalty(Quest $quest): int
    {
        if ($quest->escrow_funded_at !== null) {
            return 0;
        }

        if (! in_array($quest->status, [QuestStatus::Assigned, QuestStatus::InProgress], true)) {
            return 0;
        }

        $acceptedAt = $quest->acceptedOffer?->accepted_at;
        if (! $acceptedAt) {
            return 10;
        }

        $hours = Carbon::parse($acceptedAt)->diffInHours(now());

        return match (true) {
            $hours >= 72 => 40,
            $hours >= 48 => 30,
            $hours >= 24 => 15,
            default => 5,
        };
    }

    private function milestonePenalty(Quest $quest): int
    {
        if ($quest->status !== QuestStatus::InProgress) {
            return 0;
        }

        $due = $this->lifecycle->expectedCompletionAt($quest);
        if (! $due) {
            return 0;
        }

        if ($this->hasBlockingDispute($quest)) {
            return 0;
        }

        $now = now();

        if ($quest->delivered_at !== null && $quest->delivery_acknowledged_at === null) {
            $autoReleaseAt = $due->copy()->addHours(72);
            $hoursUntilRelease = $now->diffInHours($autoReleaseAt, false);

            if ($hoursUntilRelease <= 24 && $hoursUntilRelease > 0) {
                return 20;
            }

            if ($hoursUntilRelease <= 0) {
                return 30;
            }

            return 10;
        }

        if ($now->greaterThan($due)) {
            $daysOver = $due->diffInDays($now);

            return match (true) {
                $daysOver >= 7 => 30,
                $daysOver >= 3 => 20,
                default => 10,
            };
        }

        return 0;
    }

    private function lastActivityAt(Quest $quest): ?Carbon
    {
        $candidates = collect([
            $quest->updated_at,
            QuestConversationMessage::query()
                ->whereHas('thread', fn ($q) => $q->where('quest_id', $quest->id))
                ->max('created_at'),
            QuestOffer::query()->where('quest_id', $quest->id)->max('updated_at'),
        ])->filter()->map(fn ($d) => Carbon::parse($d));

        return $candidates->sortDesc()->first();
    }

    private function hasBlockingDispute(Quest $quest): bool
    {
        return $quest->disputes()
            ->whereIn('status', [
                QuestDisputeStatus::Open,
                QuestDisputeStatus::SelfResolving,
                QuestDisputeStatus::Escalated,
                QuestDisputeStatus::AwaitingRuling,
            ])
            ->exists();
    }
}
