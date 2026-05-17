<?php

use App\Http\Middleware\EnsureFreelancer;
use App\Http\Middleware\EnsureOperationsStaff;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RedirectOperationsStaffFromAdminConsole;
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
        channels: __DIR__.'/../routes/channels.php',
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
            'super_admin' => EnsureSuperAdmin::class,
            'operations_staff' => EnsureOperationsStaff::class,
            'redirect_operations_staff_from_admin' => RedirectOperationsStaffFromAdminConsole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reviews:lock-expired')->hourly();
        $schedule->command('quests:expire-listings')->hourly();
        $schedule->command('disputes:process-deadlines')->hourly();
        $schedule->command('freelancers:send-setup-reminders')->dailyAt('09:00');
        $schedule->command('quests:process-lifecycle')->hourly();
        $schedule->command('admin-reports:process-scheduled')->hourly();
        $schedule->command('admin-reports:refresh-aggregates')->hourly();
        $schedule->command('admin-activity-feed:prune')->daily();
        $schedule->command('promotions:refresh-badges')->weeklyOn(0, '00:00');
    })
    ->create();
