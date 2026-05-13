<?php

namespace App\Services;

use App\Enums\QuestVisibility;
use App\Models\Quest;
use App\Models\User;
use App\Models\UserFollow;
use App\Notifications\QuestAudienceNotification;
use Illuminate\Support\Collection;

class QuestPublishedNotificationService
{
    /**
     * @param  list<int>  $explicitFreelancerIds
     */
    public function notifyAudiences(Quest $quest, array $explicitFreelancerIds = []): void
    {
        $quest->loadMissing(['client', 'questCategory']);

        $notified = [];
        $visibility = $quest->visibility ?? QuestVisibility::Public;

        if ($visibility !== QuestVisibility::Private) {
            /** @var Collection<int, User> $followers */
            $followers = User::query()
                ->whereIn(
                    'id',
                    UserFollow::query()
                        ->where('following_id', $quest->client_id)
                        ->pluck('follower_id')
                )
                ->whereRelation('role', 'slug', 'freelancer')
                ->get();

            foreach ($followers as $user) {
                $notified[$user->id] = true;
                $user->notify(new QuestAudienceNotification($quest, 'follow'));
            }
        }

        $catId = (int) ($quest->quest_category_id ?? 0);
        $parentId = (int) ($quest->questCategory?->parent_id ?? 0);

        if ($visibility === QuestVisibility::Public && $catId > 0) {
            $matchers = User::query()
                ->whereRelation('role', 'slug', 'freelancer')
                ->where('users.id', '<>', $quest->client_id)
                ->whereHas('questCategoryPreferences', function ($q) use ($catId, $parentId): void {
                    $q->where(function ($w) use ($catId, $parentId): void {
                        $w->where('quest_categories.id', $catId);
                        if ($parentId > 0) {
                            $w->orWhere('quest_categories.parent_id', $parentId);
                        }
                    });
                })
                ->get();

            foreach ($matchers as $user) {
                if (isset($notified[$user->id])) {
                    continue;
                }
                $notified[$user->id] = true;
                $user->notify(new QuestAudienceNotification($quest, 'match'));
            }
        }

        foreach ($explicitFreelancerIds as $fid) {
            $fid = (int) $fid;
            if ($fid < 1 || $fid === (int) $quest->client_id) {
                continue;
            }
            $user = User::query()->whereKey($fid)->whereRelation('role', 'slug', 'freelancer')->first();
            if ($user === null) {
                continue;
            }
            if (isset($notified[$user->id])) {
                continue;
            }
            $notified[$user->id] = true;
            $user->notify(new QuestAudienceNotification($quest, 'tag'));
        }
    }

    /**
     * @param  list<int>  $freelancerIds
     */
    public function notifyTagged(Quest $quest, array $freelancerIds): void
    {
        $quest->loadMissing('client');

        foreach ($freelancerIds as $fid) {
            $fid = (int) $fid;
            if ($fid < 1 || $fid === (int) $quest->client_id) {
                continue;
            }
            $user = User::query()->whereKey($fid)->whereRelation('role', 'slug', 'freelancer')->first();
            if ($user === null) {
                continue;
            }
            $user->notify(new QuestAudienceNotification($quest, 'tag'));
        }
    }
}
