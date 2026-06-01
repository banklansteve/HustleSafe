<?php

namespace App\Console\Commands\Database\Concerns;

use App\Support\DatabaseDeletionGuard;
use Symfony\Component\Console\Input\InputOption;
use function Laravel\Prompts\confirm;

trait GuardsDestructiveDatabaseOperations
{
    public function confirmToProceed($warning = 'Application In Production', $callback = null): bool
    {
        if ($this->isProhibited()) {
            return false;
        }

        $guard = app(DatabaseDeletionGuard::class);

        if (! $guard->shouldRequireConfirmation()) {
            return true;
        }

        if ($this->hasOption('confirmed-deletion') && $this->option('confirmed-deletion')) {
            $this->components->warn('Destructive database operation confirmed via --confirmed-deletion.');
            $guard->markConfirmed();

            return true;
        }

        $database = config('database.connections.'.config('database.default').'.database', 'database');
        $operation = (string) $this->getName();

        if (! $this->input->isInteractive()) {
            $this->components->error(
                "Blocked: `{$operation}` would delete data in `{$database}`. "
                .'Run interactively and confirm, or pass --confirmed-deletion if you truly intend to wipe data.'
            );

            return false;
        }

        $this->components->alert(
            "DESTRUCTIVE OPERATION: `{$operation}` will permanently delete data in `{$database}`."
        );

        $confirmed = confirm(
            label: 'Are you sure you want to permanently delete database data?',
            default: false,
            hint: 'This cannot be undone without a backup.',
        );

        if (! $confirmed) {
            $this->components->warn('Command cancelled — no database data was deleted.');

            return false;
        }

        $guard->markConfirmed();

        return true;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function destructiveConfirmationOption(): array
    {
        return [
            'confirmed-deletion',
            null,
            InputOption::VALUE_NONE,
            'Explicitly confirm irreversible data deletion (required for non-interactive runs; never use casually)',
        ];
    }
}
