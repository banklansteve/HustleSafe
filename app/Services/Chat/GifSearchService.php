<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;

class GifSearchService
{
    public function isConfigured(): bool
    {
        return filled(config('services.tenor.api_key')) || filled(config('services.giphy.api_key'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(?string $query, int $limit = 24): array
    {
        if (filled(config('services.tenor.api_key'))) {
            $items = $this->searchTenor($query, $limit);
            if ($items !== []) {
                return $items;
            }
        }

        if (filled(config('services.giphy.api_key'))) {
            return $this->searchGiphy($query, $limit);
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchTenor(?string $query, int $limit): array
    {
        $term = trim((string) $query);
        $endpoint = $term === '' || strtolower($term) === 'trending'
            ? 'https://tenor.googleapis.com/v2/featured'
            : 'https://tenor.googleapis.com/v2/search';

        $params = [
            'key' => config('services.tenor.api_key'),
            'client_key' => config('app.name', 'hustlesafe'),
            'limit' => min(max($limit, 1), 30),
        ];

        if ($endpoint === 'https://tenor.googleapis.com/v2/search') {
            $params['q'] = $term;
        }

        $response = Http::timeout(8)->get($endpoint, $params);
        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('results', []))
            ->map(function (array $item): ?array {
                $url = data_get($item, 'media_formats.gif.url')
                    ?? data_get($item, 'media_formats.mediumgif.url')
                    ?? data_get($item, 'media_formats.tinygif.url');

                if (! $url) {
                    return null;
                }

                return [
                    'id' => (string) data_get($item, 'id'),
                    'url' => $url,
                    'preview' => data_get($item, 'media_formats.tinygif.url', $url),
                    'title' => (string) data_get($item, 'content_description', 'GIF'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchGiphy(?string $query, int $limit): array
    {
        $term = trim((string) $query);
        $endpoint = $term === '' || strtolower($term) === 'trending'
            ? 'https://api.giphy.com/v1/gifs/trending'
            : 'https://api.giphy.com/v1/gifs/search';

        $params = [
            'api_key' => config('services.giphy.api_key'),
            'limit' => min(max($limit, 1), 30),
            'rating' => 'pg',
        ];

        if ($endpoint === 'https://api.giphy.com/v1/gifs/search') {
            $params['q'] = $term;
        }

        $response = Http::timeout(8)->get($endpoint, $params);
        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('data', []))
            ->map(function (array $item): ?array {
                $url = data_get($item, 'images.fixed_height.url')
                    ?? data_get($item, 'images.downsized_medium.url')
                    ?? data_get($item, 'images.original.url');

                if (! $url) {
                    return null;
                }

                $preview = data_get($item, 'images.fixed_height_small_still.url')
                    ?? data_get($item, 'images.preview_gif.url', $url);

                return [
                    'id' => (string) data_get($item, 'id'),
                    'url' => $url,
                    'preview' => $preview,
                    'title' => (string) data_get($item, 'title', 'GIF'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
