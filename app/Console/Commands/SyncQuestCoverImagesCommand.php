<?php

namespace App\Console\Commands;

use App\Models\Quest;
use App\Services\QuestCoverService;
use Illuminate\Console\Command;

class SyncQuestCoverImagesCommand extends Command
{
    protected $signature = 'quests:sync-covers';

    protected $description = 'Recompute quests.cover_image_url from the first image attachment (or clear for default cover).';

    public function handle(QuestCoverService $cover): int
    {
        $n = 0;
        Quest::query()->chunkById(100, function ($quests) use ($cover, &$n) {
            foreach ($quests as $quest) {
                $cover->sync($quest);
                $n++;
            }
        });

        $this->info("Synced {$n} quest(s).");

        return self::SUCCESS;
    }
}
