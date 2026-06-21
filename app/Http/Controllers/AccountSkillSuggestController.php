<?php

namespace App\Http\Controllers;

use App\Services\Quest\QuestSkillDictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSkillSuggestController extends Controller
{
    public function __invoke(Request $request, QuestSkillDictionaryService $dictionary): JsonResponse
    {
        if ($request->user()?->role?->slug !== 'freelancer') {
            abort(403);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'quest_category_ids' => ['nullable', 'array', 'max:30'],
            'quest_category_ids.*' => ['integer', 'min:1'],
            'exclude' => ['nullable', 'array', 'max:30'],
            'exclude.*' => ['string', 'max:80'],
        ]);

        $user = $request->user();
        $user->loadMissing('questCategoryPreferences:id');

        $categoryIds = array_values(array_map(
            'intval',
            $validated['quest_category_ids'] ?? $user->questCategoryPreferences->pluck('id')->all(),
        ));

        return response()->json([
            'skills' => $dictionary->suggestForCategories(
                $categoryIds,
                (string) ($validated['q'] ?? ''),
                array_values($validated['exclude'] ?? []),
            ),
        ]);
    }
}
