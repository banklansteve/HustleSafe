<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\QuestFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestFileStorageService
{
    public function __construct(
        protected CloudinaryUploadService $cloudinary,
        protected QuestCoverService $cover,
    ) {}

    public function store(Quest $quest, UploadedFile $uploaded, int $sortOrder): QuestFile
    {
        if ($this->cloudinary->isConfigured()) {
            $folder = (string) config('cloudinary.folder_quests', 'hustleSafe/quests');
            $publicId = 'quest_'.$quest->id.'_'.Str::uuid()->toString();
            $out = $this->cloudinary->upload($uploaded, $folder, $publicId);

            $file = QuestFile::query()->create([
                'quest_id' => $quest->id,
                'disk' => 'cloudinary',
                'path' => $out['secure_url'],
                'cloudinary_public_id' => $out['public_id'],
                'cloudinary_resource_type' => $out['resource_type'],
                'original_name' => $uploaded->getClientOriginalName(),
                'mime_type' => $uploaded->getClientMimeType(),
                'size_bytes' => $uploaded->getSize() ?: 0,
                'sort_order' => $sortOrder,
            ]);
        } else {
            $path = $uploaded->store("quests/{$quest->id}", 'public');
            $file = QuestFile::query()->create([
                'quest_id' => $quest->id,
                'disk' => 'public',
                'path' => $path,
                'cloudinary_public_id' => null,
                'cloudinary_resource_type' => null,
                'original_name' => $uploaded->getClientOriginalName(),
                'mime_type' => $uploaded->getClientMimeType(),
                'size_bytes' => $uploaded->getSize() ?: 0,
                'sort_order' => $sortOrder,
            ]);
        }

        return $file;
    }

    public function purgeBinary(QuestFile $file): void
    {
        if ($file->cloudinary_public_id) {
            $this->cloudinary->destroy(
                $file->cloudinary_public_id,
                $file->cloudinary_resource_type
            );

            return;
        }

        if ($file->disk === 'public' && $file->path !== '' && ! preg_match('#^https?://#i', $file->path)) {
            Storage::disk('public')->delete($file->path);
        }
    }

    public function delete(QuestFile $file): void
    {
        $questId = $file->quest_id;
        $this->purgeBinary($file);
        $file->delete();

        if ($questId !== null) {
            $quest = Quest::query()->find($questId);
            if ($quest !== null) {
                $this->cover->sync($quest->fresh(['files']));
            }
        }
    }
}
