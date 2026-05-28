<?php

use App\Http\Middleware\ApplyRoleSessionLifetime;
use App\Http\Middleware\EnsureApplicationAvailable;
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
        $middleware->preventRequestsDuringMaintenance(except: [
            'admin/api/maintenance',
            'admin/api/maintenance/*',
            'login',
            'logout',
        ]);

        $middleware->priority([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            ApplyRoleSessionLifetime::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            EnsureApplicationAvailable::class,
        ]);

        $middleware->web(append: [
            EnsureApplicationAvailable::class,
            ApplyRoleSessionLifetime::class,
            HandleInertiaRequests::class,
            UpdateUserPresence::class,
            AddLinkHeadersForPreloadedAssets::class,
            ForceHttps::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paystack',
        ]);

        $middleware->alias([
            'freelancer' => EnsureFreelancer::class,
            'super_admin' => EnsureSuperAdmin::class,
            'operations_staff' => EnsureOperationsStaff::class,
            'redirect_operations_staff_from_admin' => RedirectOperationsStaffFromAdminConsole::class,
            'platform_team' => \App\Http\Middleware\EnsurePlatformTeamMember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            $status = $e->getStatusCode();

            if (! in_array($status, [403, 404], true)) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage() ?: ($status === 404 ? 'Not found.' : 'Forbidden.')], $status);
            }

            $page = $status === 404 ? 'Errors/NotFound' : 'Errors/Forbidden';

            return \Inertia\Inertia::render($page, [
                'message' => $e->getMessage() ?: null,
            ])->toResponse($request)->setStatusCode($status);
        });
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
        $schedule->command('operations:process-onboarding-assistance')->dailyAt('06:30');
        $schedule->command('customer-support:close-inactive')->everyFiveMinutes();
        $schedule->command('customer-support:send-rating-emails')->everyFiveMinutes();
        $schedule->command('conversation-monitoring:analyze-systematic')->everySixHours();
        $schedule->command('review-manipulation:refresh')->dailyAt('02:15');
        $schedule->command('review-amendments:expire')->hourly();
    })
    ->create();
