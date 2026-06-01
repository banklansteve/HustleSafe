<?php

namespace App\Support;

class DatabaseDeletionGuard
{
    private bool $confirmed = false;

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function markConfirmed(): void
    {
        $this->confirmed = true;
    }

    public function reset(): void
    {
        $this->confirmed = false;
    }

    public function shouldRequireConfirmation(): bool
    {
        if ($this->confirmed) {
            return false;
        }

        if (! config('database.destructive_commands.require_confirmation', true)) {
            $this->confirmed = true;

            return false;
        }

        if (app()->runningUnitTests()) {
            $this->confirmed = true;

            return false;
        }

        return true;
    }
}
