<?php

namespace App\Services;

use ArrayObject;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class CloudinaryUploadService
{
    protected bool $configured = false;

    public function isConfigured(): bool
    {
        if (config('cloudinary.url')) {
            return true;
        }

        return (bool) (config('cloudinary.cloud_name')
            && config('cloudinary.api_key')
            && config('cloudinary.api_secret'));
    }

    /**
     * @return array{secure_url: string, public_id: string, resource_type: string}
     */
    public function upload(UploadedFile $file, string $folder, string $publicId): array
    {
        $this->ensureConfigured();
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudinary is not configured.');
        }

        $folder = trim($folder, '/');

        $result = (new UploadApi)->upload($file->getRealPath(), [
            'folder' => $folder,
            'public_id' => $publicId,
            'overwrite' => true,
            'resource_type' => 'auto',
        ]);

        return $this->normalizeUploadResult($result);
    }

    /**
     * Cloudinary returns {@see ApiResponse} (extends ArrayObject), not a native array.
     *
     * @param  array<string, mixed>|ArrayObject<string, mixed>  $result
     * @return array{secure_url: string, public_id: string, resource_type: string}
     */
    protected function normalizeUploadResult(array|ArrayObject $result): array
    {
        $row = $result instanceof ArrayObject ? $result->getArrayCopy() : $result;

        $url = $row['secure_url'] ?? $row['url'] ?? null;
        $publicId = $row['public_id'] ?? null;
        $resourceType = $row['resource_type'] ?? 'image';

        if (! is_string($url) || $url === '' || ! is_string($publicId) || $publicId === '') {
            throw new RuntimeException('Cloudinary did not return a usable asset.');
        }

        return [
            'secure_url' => $url,
            'public_id' => $publicId,
            'resource_type' => is_string($resourceType) ? $resourceType : 'image',
        ];
    }

    public function destroy(?string $publicId, ?string $resourceType = null): void
    {
        if ($publicId === null || $publicId === '') {
            return;
        }

        $this->ensureConfigured();
        if (! $this->isConfigured()) {
            return;
        }

        $type = $resourceType !== null && $resourceType !== '' ? $resourceType : 'image';

        try {
            (new UploadApi)->destroy($publicId, [
                'resource_type' => $type,
            ]);
        } catch (\Throwable) {
            // Best-effort cleanup; avoid blocking deletes on stale IDs.
        }
    }

    protected function ensureConfigured(): void
    {
        if ($this->configured) {
            return;
        }

        $url = config('cloudinary.url');
        if (is_string($url) && $url !== '') {
            Configuration::instance($url);
            $this->configured = true;

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

        $this->configured = true;
    }
}
