<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairMigrationBootstrapCommand extends Command
{
    protected $signature = 'migrate:repair-bootstrap
                            {--force : Run migrate with --force after repair}';

    protected $description = 'Repair a partial database so quest migrations can run (creates quests table, resets skipped quest alters)';

    /**
     * Quest alter migrations that may have been recorded as "Ran" before the quests table existed.
     *
     * @var list<string>
     */
    private array $questAlterMigrations = [
        '2026_05_11_129000_create_quests_table',
        '2026_05_11_140000_add_terms_accepted_at_to_quests_table',
        '2026_05_11_160000_quest_site_context_and_offer_extras',
        '2026_05_12_200000_quest_publishing_fields_and_files',
        '2026_05_12_210000_quest_marketplace_extensions',
        '2026_05_13_100001_create_quests_table',
        '2026_05_13_120000_add_client_edit_until_to_quests_table',
        '2026_05_14_100000_quest_covers_and_cloudinary_file_meta',
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('migrations')) {
            $this->error('No migrations table found. Run php artisan migrate:install first.');

            return self::FAILURE;
        }

        $hadQuests = Schema::hasTable('quests');

        if (! $hadQuests) {
            $this->warn('Quests table is missing — clearing early quest migration records so they can run again.');
            DB::table('migrations')
                ->whereIn('migration', $this->questAlterMigrations)
                ->delete();
        }

        $this->info('Running pending migrations…');
        $code = Artisan::call('migrate', [
            '--force' => $this->option('force') || ! app()->environment('production'),
        ]);

        $this->output->write(Artisan::output());

        if ($code !== 0) {
            $this->error('Migrate failed. Fix the reported migration or restore from backup.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('quests')) {
            $this->error('Quests table still missing after migrate. Check migration logs above.');

            return self::FAILURE;
        }

        $this->info($hadQuests
            ? 'Migrate completed.'
            : 'Bootstrap repair complete — quests table is now present.');

        return self::SUCCESS;
    }
}
