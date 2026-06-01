<?php

namespace App\Console\Commands\Database;

use App\Console\Commands\Database\Concerns\GuardsDestructiveDatabaseOperations;
use Illuminate\Database\Console\Migrations\RefreshCommand;

class GuardedRefreshCommand extends RefreshCommand
{
    use GuardsDestructiveDatabaseOperations;

    protected $description = 'Reset and re-run all migrations (requires explicit confirmation — data is never wiped silently)';

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [$this->destructiveConfirmationOption()]);
    }
}
