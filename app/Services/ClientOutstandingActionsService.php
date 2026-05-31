<?php

namespace App\Services;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Schema;

class ClientOutstandingActionsService
{
    /**
     * @return list<array{key?: string, message: string, action_label: string|null, action_url: string|null}>
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

        $unseenProposalQuests = Quest::query()
            ->where('client_id', $user->id)
            ->where('status', QuestStatus::Open)
            ->whereNull('accepted_quest_offer_id')
            ->whereHas('offers', function ($query): void {
                $query->visibleInClientInbox();
                if (Schema::hasColumn('quest_offers', 'client_view_count')) {
                    $query->where('client_view_count', 0);
                }
            })
            ->orderByDesc('updated_at');

        $pendingReviews = (clone $unseenProposalQuests)->count();

        if ($pendingReviews > 0) {
            $firstQuest = (clone $unseenProposalQuests)->first(['id', 'uuid', 'slug']);

            $items[] = [
                'key' => 'unseen_proposals',
                'message' => trans_choice(
                    'You have new proposals to review on :count open quest.|You have new proposals to review on :count open quests.',
                    $pendingReviews,
                    ['count' => $pendingReviews]
                ),
                'action_label' => $pendingReviews === 1
                    ? __('Review proposals')
                    : __('Open my quests'),
                'action_url' => $pendingReviews === 1 && $firstQuest
                    ? route('quests.client.proposals.index', $firstQuest)
                    : route('quests.index'),
            ];
        }

        return $items;
    }
}
