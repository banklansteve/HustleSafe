<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\QuestBookmark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestBookmarkController extends Controller
{
    public function store(Request $request, Quest $quest): RedirectResponse
    {
        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user->role?->slug !== 'freelancer') {
            abort(403);
        }

        $exists = QuestBookmark::query()
            ->where('quest_id', $quest->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $exists) {
            QuestBookmark::query()->create([
                'quest_id' => $quest->id,
                'user_id' => $user->id,
            ]);
            $quest->increment('saves_count');
        }

        return back()->with('success', __('Quest saved to your list.'));
    }

    public function destroy(Request $request, Quest $quest): RedirectResponse
    {
        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user->role?->slug !== 'freelancer') {
            abort(403);
        }

        $deleted = QuestBookmark::query()
            ->where('quest_id', $quest->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted > 0) {
            if ((int) $quest->saves_count > 0) {
                $quest->decrement('saves_count');
            }
        }

        return back()->with('success', __('Removed from saved quests.'));
    }
}
