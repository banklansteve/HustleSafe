<?php

namespace App\Services;

use App\Models\Quest;
use Illuminate\Support\Str;

class QuestSlugService
{
    public function uniqueSlugFromTitle(string $title, ?int $exceptQuestId = null): string
    {
        $base = Str::slug(Str::limit(trim($title), 80, ''));
        if ($base === '') {
            $base = 'quest';
        }

        $slug = $base;
        $n = 2;
        while ($this->slugTaken($slug, $exceptQuestId)) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }

    protected function slugTaken(string $slug, ?int $exceptQuestId): bool
    {
        $q = Quest::query()->where('slug', $slug);
        if ($exceptQuestId !== null) {
            $q->where('id', '<>', $exceptQuestId);
        }

        return $q->exists();
    }
}
