<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Services\QuestFormFieldProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestFieldProfileController extends Controller
{
    public function __invoke(Request $request, QuestFormFieldProfileService $profiles): JsonResponse
    {
        if (! $request->user()?->can('create', Quest::class)) {
            abort(403);
        }

        $id = (int) $request->query('quest_category_id', 0);

        return response()->json($profiles->profileForLeafCategoryId($id > 0 ? $id : null));
    }
}
