<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\QuestBookmark;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestBookmarkController extends Controller
{
    public function store(Request $request, Quest $quest): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user->role?->slug !== 'freelancer') {
            abort(403);
        }

        try {
            $bookmark = QuestBookmark::query()->firstOrCreate([
                'quest_id' => $quest->id,
                'user_id' => $user->id,
            ]);

            if ($bookmark->wasRecentlyCreated) {
                $quest->increment('saves_count');
            }
        } catch (QueryException $exception) {
            report($exception);

            return $this->bookmarkError($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'bookmarked' => true,
                'saves_count' => $this->savesCount($quest),
                'message' => __('Quest saved to your list.'),
            ]);
        }

        return back()->with('success', __('Quest saved to your list.'));
    }

    public function destroy(Request $request, Quest $quest): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user->role?->slug !== 'freelancer') {
            abort(403);
        }

        try {
            $deleted = QuestBookmark::query()
                ->where('quest_id', $quest->id)
                ->where('user_id', $user->id)
                ->delete();

            if ($deleted > 0 && (int) $quest->saves_count > 0) {
                $quest->decrement('saves_count');
            }
        } catch (QueryException $exception) {
            report($exception);

            return $this->bookmarkError($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'bookmarked' => false,
                'saves_count' => $this->savesCount($quest),
                'message' => __('Removed from saved quests.'),
            ]);
        }

        return back()->with('success', __('Removed from saved quests.'));
    }

    private function bookmarkError(Request $request): RedirectResponse|JsonResponse
    {
        $message = __('We could not update this saved quest right now. Please wait a few seconds and try again.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return back()->withErrors(['bookmark' => $message]);
    }

    private function savesCount(Quest $quest): int
    {
        return (int) Quest::query()->whereKey($quest->id)->value('saves_count');
    }
}
