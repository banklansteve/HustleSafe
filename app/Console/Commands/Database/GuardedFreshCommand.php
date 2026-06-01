<?php

namespace App\Console\Commands\Database;

use App\Console\Commands\Database\Concerns\GuardsDestructiveDatabaseOperations;
use Illuminate\Database\Console\Migrations\FreshCommand;

class GuardedFreshCommand extends FreshCommand
{
    use GuardsDestructiveDatabaseOperations;

    protected $description = 'Drop all tables and re-run all migrations (requires explicit confirmation — data is never wiped silently)';

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [$this->destructiveConfirmationOption()]);
    }
}
