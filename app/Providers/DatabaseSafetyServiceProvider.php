<?php

namespace App\Providers;

use App\Support\DatabaseDeletionGuard;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DatabaseSafetyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseDeletionGuard::class);
    }

    public function boot(): void
    {
        Event::listen(CommandFinished::class, function (): void {
            if ($this->app->bound(DatabaseDeletionGuard::class)) {
                $this->app->make(DatabaseDeletionGuard::class)->reset();
            }
        });
    }
}
