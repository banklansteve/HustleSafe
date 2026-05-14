<?php

namespace App\Services;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class ClientOutstandingActionsService
{
    /**
     * @return list<array{message: string, action_label: string|null, action_url: string|null}>
     */
    public function items(User $user): array
    {
        if ($user->role?->slug !== 'client') {
            return [];
        }

        $items = [];

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $items[] = [
                'message' => __('Verify your email so freelancers can trust your briefs and you receive proposal alerts reliably.'),
                'action_label' => __('Resend / verify'),
                'action_url' => route('verification.notice'),
            ];
        }

        $pendingReviews = Quest::query()
            ->where('client_id', $user->id)
            ->where('status', QuestStatus::Open)
            ->where('offers_count', '>', 0)
            ->whereNull('accepted_quest_offer_id')
            ->count();

        if ($pendingReviews > 0) {
            $items[] = [
                'message' => trans_choice(
                    'You have new proposals to review on :count open quest.|You have new proposals to review on :count open quests.',
                    $pendingReviews,
                    ['count' => $pendingReviews]
                ),
                'action_label' => __('Open my quests'),
                'action_url' => route('quests.index'),
            ];
        }

        return $items;
    }
}
