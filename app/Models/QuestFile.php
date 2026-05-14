<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class QuestFile extends Model
{
    protected $fillable = [
        'quest_id',
        'disk',
        'path',
        'cloudinary_public_id',
        'cloudinary_resource_type',
        'original_name',
        'mime_type',
        'size_bytes',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Quest, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function url(): string
    {
        $path = trim((string) $this->path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        if (str_starts_with($path, '//')) {
            return 'https:'.$path;
        }

        $generated = Storage::disk($this->disk)->url($path);

        if (preg_match('#^https?://#i', $generated)) {
            return $generated;
        }

        return url($generated);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
