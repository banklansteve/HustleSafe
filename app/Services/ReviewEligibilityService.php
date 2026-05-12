<?php

namespace App\Services;

use App\Enums\QuestStatus;
use App\Enums\ReviewType;
use App\Models\Quest;
use App\Models\User;

class ReviewEligibilityService
{
    public function canReview(User $reviewer, Quest $quest): bool
    {
        if (! $quest->isParty($reviewer)) {
            return false;
        }

        return collect(QuestStatus::reviewEligibleStatuses())
            ->contains(fn (QuestStatus $s) => $s === $quest->status);
    }

    public function resolveReviewType(Quest $quest): ReviewType
    {
        if ($quest->status === QuestStatus::Completed) {
            return ReviewType::Full;
        }

        return ReviewType::Partial;
    }

    public function expectedReviewee(User $reviewer, Quest $quest): ?User
    {
        return $quest->oppositeParty($reviewer);
    }
}
