<?php

namespace App\Console\Commands\Database;

use App\Console\Commands\Database\Concerns\GuardsDestructiveDatabaseOperations;
use Illuminate\Database\Console\WipeCommand;

class GuardedWipeCommand extends WipeCommand
{
    use GuardsDestructiveDatabaseOperations;

    protected $description = 'Drop all tables, views, and types (requires explicit confirmation — data is never wiped silently)';

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [$this->destructiveConfirmationOption()]);
    }
}
