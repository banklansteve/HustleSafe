<?php

namespace App\Http\Controllers;

use App\Services\Quest\QuestBudgetGuidanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestBudgetGuidanceController extends Controller
{
    public function __invoke(Request $request, QuestBudgetGuidanceService $guidance): JsonResponse
    {
        $categoryId = $request->integer('quest_category_id') ?: null;

        return response()->json($guidance->forCategoryId($categoryId));
    }
}
