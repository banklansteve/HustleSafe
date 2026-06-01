<?php

namespace App\Console\Commands;

use Database\Seeders\BaselineDatabaseSeeder;
use Illuminate\Console\Command;

class SeedBaselineDatabaseCommand extends Command
{
    protected $signature = 'db:seed-baseline
                            {--force : Run without confirmation in production}';

    protected $description = 'Seed baseline reference data (roles, Nigeria geo, quest categories, support templates)';

    public function handle(): int
    {
        $this->components->info('Seeding baseline reference data…');

        $this->call('db:seed', [
            '--class' => BaselineDatabaseSeeder::class,
            '--force' => $this->option('force') || ! $this->laravel->environment('production'),
        ]);

        $this->components->info('Baseline seed complete.');

        return self::SUCCESS;
    }
}
