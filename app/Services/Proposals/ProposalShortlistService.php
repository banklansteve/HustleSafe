<?php

namespace App\Services\Proposals;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\ProposalShortlistedFreelancerNotification;
use App\Support\PlatformSettings;
use Illuminate\Validation\ValidationException;

class ProposalShortlistService
{
    public function maxPerQuest(): int
    {
        return max(1, PlatformSettings::int('quests.shortlist_max_per_quest', 5));
    }

    public function countForQuest(Quest $quest): int
    {
        return QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->where('status', 'shortlisted')
            ->count();
    }

    public function isShortlisted(QuestOffer $offer): bool
    {
        return $offer->status === 'shortlisted';
    }

    /**
     * @return array{shortlisted: bool, count: int, max: int}
     */
    public function toggle(Quest $quest, QuestOffer $offer): array
    {
        if ($quest->status !== QuestStatus::Open) {
            throw ValidationException::withMessages([
                'shortlist' => __('This quest is not accepting shortlist changes right now.'),
            ]);
        }

        if ($this->isShortlisted($offer)) {
            $offer->update([
                'status' => 'submitted',
                'shortlisted_at' => null,
            ]);

            return [
                'shortlisted' => false,
                'count' => $this->countForQuest($quest),
                'max' => $this->maxPerQuest(),
            ];
        }

        if (! in_array($offer->status, ['submitted', 'shortlisted'], true)) {
            throw ValidationException::withMessages([
                'shortlist' => __('Only open proposals can be shortlisted.'),
            ]);
        }

        $max = $this->maxPerQuest();
        if ($this->countForQuest($quest) >= $max) {
            throw ValidationException::withMessages([
                'shortlist' => __('You can shortlist at most :max proposals on this quest.', ['max' => $max]),
            ]);
        }

        $offer->update([
            'status' => 'shortlisted',
            'shortlisted_at' => now(),
            'client_pinned_at' => null,
        ]);

        $offer->freelancer?->notify(new ProposalShortlistedFreelancerNotification($offer));

        return [
            'shortlisted' => true,
            'count' => $this->countForQuest($quest),
            'max' => $max,
        ];
    }

    public function hasActiveShortlist(Quest $quest): bool
    {
        return $this->countForQuest($quest) > 0;
    }

    public function awardNudgeGraceDays(): int
    {
        return max(1, PlatformSettings::int('quests.shortlist_award_nudge_days_after_deadline', 7));
    }

    public function noShortlistReviewDays(): int
    {
        return max(1, PlatformSettings::int('quests.proposals_no_shortlist_review_days', 5));
    }
}
