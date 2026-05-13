<?php

use App\Http\Middleware\EnsureFreelancer;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\UpdateUserPresence;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            UpdateUserPresence::class,
            AddLinkHeadersForPreloadedAssets::class,
            ForceHttps::class,
        ]);

        $middleware->alias([
            'freelancer' => EnsureFreelancer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reviews:lock-expired')->hourly();
        $schedule->command('quests:expire-listings')->hourly();
        $schedule->command('freelancers:send-setup-reminders')->dailyAt('09:00');
    })
    ->create();
