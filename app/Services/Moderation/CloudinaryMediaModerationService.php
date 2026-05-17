<?php

namespace App\Services\Moderation;

use App\Models\PortfolioFile;
use App\Services\CloudinaryUploadService;
use Cloudinary\Api\Upload\UploadApi;

class CloudinaryMediaModerationService
{
    public function scanPortfolioFile(PortfolioFile $file): array
    {
        if (! $file->isImage() || ! $file->cloudinary_public_id) {
            return [];
        }

        if (! app(CloudinaryUploadService::class)->isConfigured()) {
            return [];
        }

        try {
            $result = (new UploadApi)->explicit($file->cloudinary_public_id, [
                'resource_type' => $file->cloudinary_resource_type ?: 'image',
                'type' => 'upload',
                'moderation' => 'aws_rek',
            ]);

            $row = method_exists($result, 'getArrayCopy') ? $result->getArrayCopy() : (array) $result;
            $labels = collect($row['moderation'] ?? [])
                ->flatMap(fn ($item) => $item['response']['moderation_labels'] ?? $item['response']['moderationLabels'] ?? [])
                ->map(fn ($label) => [
                    'name' => $label['name'] ?? $label['Name'] ?? 'Unknown',
                    'confidence' => (int) round((float) ($label['confidence'] ?? $label['Confidence'] ?? 0)),
                ])
                ->filter(fn ($label) => $label['confidence'] >= 70)
                ->values()
                ->all();

            return $labels;
        } catch (\Throwable) {
            return [];
        }
    }
}
