<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\StoreQuestFileRequest;
use App\Models\Quest;
use App\Models\QuestFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class QuestFileController extends Controller
{
    public function store(StoreQuestFileRequest $request, Quest $quest): RedirectResponse
    {
        if ($quest->files()->count() >= 10) {
            return back()->withErrors(['file' => __('You can attach up to 10 files per quest.')]);
        }

        $uploaded = $request->file('file');
        $path = $uploaded->store("quests/{$quest->id}", 'public');
        $maxSort = (int) $quest->files()->max('sort_order');

        QuestFile::query()->create([
            'quest_id' => $quest->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $uploaded->getClientOriginalName(),
            'mime_type' => $uploaded->getClientMimeType(),
            'size_bytes' => $uploaded->getSize() ?: 0,
            'sort_order' => $maxSort + 1,
        ]);

        return back()->with('success', __('File uploaded.'));
    }

    public function destroy(Quest $quest, QuestFile $file): RedirectResponse
    {
        $this->authorize('update', $quest);

        if ($file->quest_id !== $quest->id) {
            abort(404);
        }

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return back()->with('success', __('File removed.'));
    }
}
