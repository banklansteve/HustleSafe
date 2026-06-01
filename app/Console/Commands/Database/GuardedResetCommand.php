<?php

namespace App\Console\Commands\Database;

use App\Console\Commands\Database\Concerns\GuardsDestructiveDatabaseOperations;
use Illuminate\Database\Console\Migrations\ResetCommand;

class GuardedResetCommand extends ResetCommand
{
    use GuardsDestructiveDatabaseOperations;

    protected $description = 'Rollback all database migrations (requires explicit confirmation — tables and data may be dropped)';

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [$this->destructiveConfirmationOption()]);
    }
}
