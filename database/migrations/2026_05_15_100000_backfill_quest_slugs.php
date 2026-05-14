<?php

use App\Models\Quest;
use App\Services\QuestSlugService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quests') || ! Schema::hasColumn('quests', 'slug')) {
            return;
        }

        $service = app(QuestSlugService::class);

        Quest::query()
            ->where(function ($q): void {
                $q->whereNull('slug')->orWhere('slug', '');
            })
            ->orderBy('id')
            ->each(function (Quest $quest) use ($service): void {
                $base = $quest->title ?: 'quest';
                $quest->forceFill([
                    'slug' => $service->uniqueSlugFromTitle($base, $quest->id),
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        // Intentionally left blank — do not strip slugs after deploy.
    }
};
