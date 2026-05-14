<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\QuestFile;

class QuestCoverService
{
    /**
     * Set quest.cover_image_url from the first image attachment, or null for default cover.
     */
    public function sync(Quest $quest): void
    {
        $quest->load(['files' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        $firstImage = $quest->files->first(fn (QuestFile $f) => $f->isImage());

        $quest->forceFill([
            'cover_image_url' => $firstImage !== null ? $firstImage->url() : null,
        ])->saveQuietly();
    }
}
