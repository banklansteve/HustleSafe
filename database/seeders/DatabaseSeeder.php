<?php

namespace Database\Seeders;

use App\Models\LocalGovernment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(NigeriaGeoSeeder::class);
        $this->call(QuestCategorySeeder::class);

        $lga = LocalGovernment::query()->with('state')->first();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'account_type' => 'sponsor',
            'state_id' => $lga?->state_id,
            'local_government_id' => $lga?->id,
        ]);
    }
}
