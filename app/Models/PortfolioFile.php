<?php

namespace App\Models;

use App\Services\CloudinaryUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PortfolioFile extends Model
{
    protected $fillable = [
        'portfolio_id',
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
     * @return BelongsTo<Portfolio, $this>
     */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
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

        $disk = $this->disk ?? 'public';
        $generated = Storage::disk($disk)->url($path);

        if (preg_match('#^https?://#i', $generated)) {
            return $generated;
        }

        return url($generated);
    }

    public function purgeFromStorage(): void
    {
        if ($this->cloudinary_public_id) {
            app(CloudinaryUploadService::class)->destroy(
                $this->cloudinary_public_id,
                $this->cloudinary_resource_type
            );

            return;
        }

        $disk = $this->disk ?? 'public';
        if ($disk === 'public' && $this->path !== '' && ! preg_match('#^https?://#i', $this->path)) {
            Storage::disk('public')->delete($this->path);
        }
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }
}
