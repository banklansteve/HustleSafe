<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Services\Quest\QuestDescriptionAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class QuestDescriptionSuggestionController extends Controller
{
    public function __invoke(Request $request, QuestDescriptionAiService $service): JsonResponse
    {
        if (! $request->user()?->can('create', Quest::class)) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'quest_category_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $suggestions = $service->suggestForQuest(
                trim($validated['title']),
                isset($validated['quest_category_id']) ? (int) $validated['quest_category_id'] : null,
                $validated['notes'] ?? null,
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'available' => true,
                'powered_by_claude' => false,
                'message' => __('Could not generate suggestions right now. Please try again shortly.'),
                'suggestions' => [],
            ], 500);
        }

        if ($suggestions === []) {
            return response()->json([
                'available' => true,
                'powered_by_claude' => $service->usesClaude(),
                'message' => __('No suggestions were returned. Try again.'),
                'suggestions' => [],
            ]);
        }

        return response()->json([
            'available' => true,
            'powered_by_claude' => $service->usesClaude(),
            'suggestions' => $suggestions,
        ]);
    }
}
