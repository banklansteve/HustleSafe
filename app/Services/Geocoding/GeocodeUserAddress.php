<?php

namespace App\Services\Geocoding;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves approximate latitude/longitude from address fields (OpenStreetMap Nominatim).
 * Respect usage policy: https://operations.osmfoundation.org/policies/nominatim/
 */
class GeocodeUserAddress
{
    public function __invoke(User $user): void
    {
        $user->loadMissing(['stateModel', 'localGovernmentModel']);

        $parts = array_filter([
            $user->address_line,
            $user->city,
            $user->localGovernmentModel?->name,
            $user->stateModel?->name,
            'Nigeria',
        ], fn (?string $p) => $p !== null && trim($p) !== '');

        if (count($parts) < 2) {
            return;
        }

        $query = implode(', ', $parts);

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('services.nominatim.user_agent', config('app.name').' ('.config('app.url', 'http://localhost').')'),
                'Accept-Language' => 'en',
            ])
                ->timeout(8)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if (! $response->successful()) {
                return;
            }

            $rows = $response->json();
            if (! is_array($rows) || $rows === []) {
                return;
            }

            $lat = $rows[0]['lat'] ?? null;
            $lon = $rows[0]['lon'] ?? null;
            if ($lat === null || $lon === null) {
                return;
            }

            $user->forceFill([
                'latitude' => (float) $lat,
                'longitude' => (float) $lon,
                'geocoded_at' => now(),
            ])->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('Geocoding failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
