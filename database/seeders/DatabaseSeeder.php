<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Default application seeder — baseline reference data only.
     * Use FakeMarketplaceUsersSeeder or SponsorUsersSeeder separately for demo accounts.
     */
    public function run(): void
    {
        $this->call(BaselineDatabaseSeeder::class);
    }
}
