<?php

namespace App\Console\Commands;

use App\Services\Admin\MaintenanceModeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PlatformMaintenanceCommand extends Command
{
    protected $signature = 'platform:maintenance
                            {action : on or off}
                            {--message= : Public maintenance message}
                            {--return-time= : Estimated return (shown on maintenance page)}';

    protected $description = 'Toggle HustleSafe maintenance mode (database flag only — does not use artisan down)';

    public function handle(MaintenanceModeService $maintenance): int
    {
        $action = strtolower((string) $this->argument('action'));

        if ($action === 'off') {
            $maintenance->disable();
            $this->clearLegacyArtisanDown();
            $this->info('Maintenance mode is OFF. The site is live.');

            return self::SUCCESS;
        }

        if ($action === 'on') {
            $maintenance->enable(
                $this->option('message'),
                $this->option('return-time'),
            );
            $this->clearLegacyArtisanDown();
            $this->info('Maintenance mode is ON. Public users see the custom workshop page.');
            $this->line('Super admins: log in at /login then open /admin. Staff: /operations after login.');

            return self::SUCCESS;
        }

        $this->error('Use action "on" or "off".');

        return self::FAILURE;
    }

    private function clearLegacyArtisanDown(): void
    {
        if (! app()->isDownForMaintenance()) {
            return;
        }

        Artisan::call('up');
        $this->warn('Removed legacy artisan down file (storage/framework/maintenance.php).');
    }
}
