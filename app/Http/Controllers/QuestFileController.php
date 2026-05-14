<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\StoreQuestFileRequest;
use App\Models\Quest;
use App\Models\QuestFile;
use App\Services\QuestCoverService;
use App\Services\QuestFileStorageService;
use Illuminate\Http\RedirectResponse;

class QuestFileController extends Controller
{
    public function store(
        StoreQuestFileRequest $request,
        Quest $quest,
        QuestFileStorageService $questFiles,
        QuestCoverService $cover,
    ): RedirectResponse {
        if ($quest->files()->count() >= 10) {
            return back()->withErrors(['file' => __('You can attach up to 10 files per quest.')]);
        }

        $uploaded = $request->file('file');
        $maxSort = (int) $quest->files()->max('sort_order');

        $questFiles->store($quest, $uploaded, $maxSort + 1);
        $cover->sync($quest->fresh(['files']));

        return back()->with('success', __('File uploaded.'));
    }

    public function destroy(Quest $quest, QuestFile $file, QuestFileStorageService $questFiles): RedirectResponse
    {
        $this->authorize('update', $quest);

        if ($file->quest_id !== $quest->id) {
            abort(404);
        }

        $questFiles->delete($file);

        return back()->with('success', __('File removed.'));
    }
}
