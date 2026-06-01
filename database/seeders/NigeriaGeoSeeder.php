<?php

namespace Database\Seeders;

use App\Models\LocalGovernment;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class NigeriaGeoSeeder extends Seeder
{
    /**
     * Seeds states + LGAs from {@see database/data/nigeria_states_lgas.json}
     * (curated public dataset; deduped & lightly corrected — target ~774 LGAs).
     */
    public function run(): void
    {
        $path = database_path('data/nigeria_states_lgas.json');
        if (! File::exists($path)) {
            $this->command?->error('Missing database/data/nigeria_states_lgas.json — run download or add file.');

            return;
        }

        $raw = json_decode(File::get($path), true);
        if (! is_array($raw)) {
            $this->command?->error('Invalid JSON in nigeria_states_lgas.json');

            return;
        }

        $lgaBlacklistByState = [
            'Lagos' => ['yewa-south'],
        ];

        foreach ($raw as $row) {
            $code = $row['code'] ?? '';
            $stateName = $this->normalizeStateName((string) ($row['name'] ?? ''));
            $lgas = $row['lgas'] ?? [];

            if ($stateName === '' || $code === '') {
                continue;
            }

            $state = State::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $stateName],
            );

            $seen = [];
            foreach ($lgas as $lgaName) {
                $name = trim((string) $lgaName);
                if ($name === '') {
                    continue;
                }

                $key = mb_strtolower($name);
                if (isset($seen[$key])) {
                    continue;
                }

                $blacklist = $lgaBlacklistByState[$stateName] ?? [];
                if (in_array($key, $blacklist, true)) {
                    continue;
                }

                $seen[$key] = true;

                LocalGovernment::query()->updateOrCreate(
                    [
                        'state_id' => $state->id,
                        'name' => $name,
                    ],
                    [],
                );
            }
        }

        $lgaCount = LocalGovernment::query()->count();
        $stateCount = State::query()->count();
        $this->command?->info("Nigeria geo seeded: {$lgaCount} LGAs across {$stateCount} states.");

        if ($stateCount !== 37) {
            $this->command?->warn("Expected 36 states plus FCT (37 records); found {$stateCount}. Check database/data/nigeria_states_lgas.json.");
        }

        if ($lgaCount < 774) {
            $this->command?->warn("Expected ~776 LGAs; found {$lgaCount}. Some LGAs may be missing from the dataset.");
        }
    }

    protected function normalizeStateName(string $name): string
    {
        $name = trim($name);

        return match ($name) {
            'Abuja' => 'FCT',
            'AkwaIbom' => 'Akwa Ibom',
            'CrossRiver' => 'Cross River',
            'Nassarawa' => 'Nasarawa',
            default => $name,
        };
    }
}
