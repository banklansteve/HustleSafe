<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class CloudinaryAvatarService
{
    public function __construct(
        protected CloudinaryUploadService $uploads,
    ) {}

    public function isConfigured(): bool
    {
        return $this->uploads->isConfigured();
    }

    /**
     * Upload profile photo; returns HTTPS URL for storage on users.avatar_url.
     */
    public function uploadAvatar(UploadedFile $file, int $userId): string
    {
        if (! $this->uploads->isConfigured()) {
            throw new RuntimeException('Cloudinary is not configured. Set CLOUDINARY_URL or CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET.');
        }

        $folder = trim((string) config('cloudinary.folder_profiles', 'hustleSafe/profiles'), '/');
        $publicId = 'user_'.$userId.'_'.time();

        $out = $this->uploads->upload($file, $folder, $publicId);

        return $out['secure_url'];
    }
}
