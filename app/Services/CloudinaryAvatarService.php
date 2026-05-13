<?php

namespace App\Services;

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class CloudinaryAvatarService
{
    public function __construct()
    {
        $this->configure();
    }

    public function isConfigured(): bool
    {
        if (config('cloudinary.url')) {
            return true;
        }

        return config('cloudinary.cloud_name')
            && config('cloudinary.api_key')
            && config('cloudinary.api_secret');
    }

    /**
     * Upload avatar image; returns HTTPS URL for storage on users.avatar_url.
     */
    public function uploadAvatar(UploadedFile $file, int $userId): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudinary is not configured. Set CLOUDINARY_URL or CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET.');
        }

        $folder = trim((string) config('cloudinary.folder', 'hustlesafe'), '/').'/avatars';

        $result = (new UploadApi)->upload($file->getRealPath(), [
            'folder' => $folder,
            'public_id' => 'user_'.$userId.'_'.time(),
            'overwrite' => true,
            'resource_type' => 'image',
        ]);

        $url = $result['secure_url'] ?? $result['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Cloudinary did not return an image URL.');
        }

        return $url;
    }

    protected function configure(): void
    {
        $url = config('cloudinary.url');
        if (is_string($url) && $url !== '') {
            Configuration::instance($url);

            return;
        }

        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if ($cloudName && $apiKey && $apiSecret) {
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => [
                    'secure' => true,
                ],
            ]);
        }
    }
}
