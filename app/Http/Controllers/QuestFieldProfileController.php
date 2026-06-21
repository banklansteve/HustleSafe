<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Services\Quest\QuestPreferenceProfileService;
use App\Services\Quest\QuestRecurringEngagementService;
use App\Services\QuestFormFieldProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestFieldProfileController extends Controller
{
    public function __invoke(
        Request $request,
        QuestFormFieldProfileService $profiles,
        QuestPreferenceProfileService $preferenceProfiles,
        QuestRecurringEngagementService $recurring,
    ): JsonResponse {
        if (! $request->user()?->can('create', Quest::class)) {
            abort(403);
        }

        $id = (int) $request->query('quest_category_id', 0);
        $leafId = $id > 0 ? $id : null;
        $leaf = $leafId ? \App\Models\QuestCategory::query()->with('parent')->find($leafId) : null;

        return response()->json(array_merge(
            $profiles->profileForLeafCategoryId($leafId),
            [
                'preferences' => $preferenceProfiles->profileForLeafCategoryId($leafId),
                'recurring_engagement' => $recurring->profilePayload($leaf),
            ],
        ));
    }
}
