<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Throwable;

class ChatAttachmentHelper
{
    /**
     * @param  list<array<string, mixed>>|null  $attachments
     * @return list<array<string, mixed>>
     */
    public static function normalizeList(?array $attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        return collect($attachments)
            ->map(fn ($att) => is_array($att) ? self::normalizeOne($att) : $att)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array<string, mixed>
     */
    public static function normalizeOne(array $attachment): array
    {
        $path = trim((string) ($attachment['path'] ?? ''));
        $url = trim((string) ($attachment['url'] ?? ''));
        $remote = (bool) ($attachment['remote'] ?? false);
        $type = (string) ($attachment['type'] ?? '');

        if ($remote || $type === 'gif') {
            if ($url !== '') {
                $attachment['url'] = $url;
            }
            $attachment['mime'] = $attachment['mime'] ?? 'image/gif';

            return $attachment;
        }

        if ($path !== '') {
            $attachment['url'] = self::publicUrlForPath($path);
        } elseif ($url !== '' && ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://') && ! str_starts_with($url, '//')) {
            $attachment['url'] = str_starts_with($url, '/storage/')
                ? $url
                : self::publicUrlForPath(ltrim($url, '/'));
        }

        return $attachment;
    }

    public static function publicUrlForPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $relative = '/storage/'.$path;

        if (! app()->runningInConsole() && ! app()->runningUnitTests()) {
            try {
                return url($relative);
            } catch (Throwable) {
                // fall through
            }
        }

        return $relative;
    }

    /**
     * @return array<string, mixed>
     */
    public static function remoteGif(string $url, ?string $title = null): array
    {
        return [
            'type' => 'gif',
            'remote' => true,
            'url' => $url,
            'name' => $title ?: 'GIF',
            'mime' => 'image/gif',
        ];
    }

    /**
     * @param  array<int, UploadedFile>|null  $files
     * @return list<array<string, mixed>>
     */
    public static function storeUploadedFiles(?array $files, string $directory): array
    {
        if ($files === null) {
            return [];
        }

        $stored = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store($directory.'/'.date('Y/m'), 'public');
            $stored[] = [
                'path' => $path,
                'url' => self::publicUrlForPath($path),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return $stored;
    }
}
