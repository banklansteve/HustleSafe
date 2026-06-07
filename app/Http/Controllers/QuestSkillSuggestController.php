<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Services\Quest\QuestSkillDictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestSkillSuggestController extends Controller
{
    public function __invoke(Request $request, QuestSkillDictionaryService $dictionary): JsonResponse
    {
        if (! $request->user()?->can('create', Quest::class)) {
            abort(403);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'quest_category_id' => ['nullable', 'integer', 'min:1'],
            'exclude' => ['nullable', 'array', 'max:10'],
            'exclude.*' => ['string', 'max:80'],
        ]);

        $leafId = isset($validated['quest_category_id']) ? (int) $validated['quest_category_id'] : null;

        return response()->json([
            'skills' => $dictionary->suggest(
                $leafId,
                (string) ($validated['q'] ?? ''),
                array_values($validated['exclude'] ?? []),
            ),
        ]);
    }
}
