<?php

namespace App\Console\Commands;

use App\Models\Quest;
use App\Models\QuestFile;
use App\Services\CloudinaryUploadService;
use App\Services\QuestCoverService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateQuestFilesToCloudinaryCommand extends Command
{
    protected $signature = 'quests:migrate-files-to-cloudinary
                            {--dry-run : Show what would be migrated without uploading}';

    protected $description = 'Re-upload local quest_files from the public disk to Cloudinary (hustleSafe/quests) and update DB rows.';

    public function handle(
        CloudinaryUploadService $cloudinary,
        QuestCoverService $cover,
    ): int {
        if (! $cloudinary->isConfigured()) {
            $this->error('Cloudinary is not configured. Set CLOUDINARY_URL or CLOUDINARY_CLOUD_NAME, API_KEY, and API_SECRET.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        $query = QuestFile::query()
            ->where('disk', 'public')
            ->whereNull('cloudinary_public_id')
            ->whereNotNull('path')
            ->orderBy('id');

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('No local quest files to migrate.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} quest file(s) on the public disk.");

        $folder = (string) config('cloudinary.folder_quests', 'hustleSafe/quests');
        $affectedQuests = [];

        foreach ($query->cursor() as $file) {
            /** @var QuestFile $file */
            $path = $file->path;
            if (preg_match('#^https?://#i', $path)) {
                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $this->warn("Missing on disk, skipping id={$file->id} path={$path}");

                continue;
            }

            $abs = Storage::disk('public')->path($path);
            $publicId = 'quest_'.$file->quest_id.'_mig_'.Str::uuid()->toString();

            if ($dry) {
                $this->line("[dry-run] Would upload quest_file id={$file->id} to {$folder}/{$publicId}");

                continue;
            }

            try {
                $uploaded = new UploadedFile(
                    $abs,
                    $file->original_name,
                    $file->mime_type,
                    null,
                    true
                );
                $out = $cloudinary->upload($uploaded, $folder, $publicId);

                $file->forceFill([
                    'disk' => 'cloudinary',
                    'path' => $out['secure_url'],
                    'cloudinary_public_id' => $out['public_id'],
                    'cloudinary_resource_type' => $out['resource_type'],
                ])->save();

                Storage::disk('public')->delete($path);
                $affectedQuests[$file->quest_id] = true;

                $this->info("Migrated quest_file id={$file->id}");
            } catch (\Throwable $e) {
                $this->error("Failed id={$file->id}: ".$e->getMessage());
            }
        }

        if (! $dry) {
            foreach (array_keys($affectedQuests) as $questId) {
                $quest = Quest::query()->find($questId);
                if ($quest !== null) {
                    $cover->sync($quest);
                }
            }
        }

        $this->info($dry ? 'Dry run complete.' : 'Migration complete.');

        return self::SUCCESS;
    }
}
