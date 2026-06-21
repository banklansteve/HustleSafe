<?php

namespace App\Services\Matching;

use App\Enums\QuestFreelancerLocationPref;
use App\Models\Quest;
use App\Services\QuestFormFieldProfileService;

/**
 * Decides when quest–freelancer matching should ignore geography.
 */
final class QuestRemoteMatchPolicy
{
    public function __construct(
        protected QuestFormFieldProfileService $fieldProfiles,
    ) {}

    public function isLocationAgnostic(Quest $quest): bool
    {
        $pref = $quest->freelancer_location_pref;

        if ($pref instanceof QuestFreelancerLocationPref) {
            if ($pref === QuestFreelancerLocationPref::RemoteFriendly) {
                return true;
            }

            if ($pref === QuestFreelancerLocationPref::LocalOnly) {
                return false;
            }
        } else {
            $value = (string) $pref;

            if ($value === QuestFreelancerLocationPref::RemoteFriendly->value) {
                return true;
            }

            if ($value === QuestFreelancerLocationPref::LocalOnly->value) {
                return false;
            }
        }

        $profile = $this->fieldProfiles->profileForLeafCategoryId(
            (int) ($quest->quest_category_id ?? 0),
        );

        return ! empty($profile['remote_first']);
    }
}
