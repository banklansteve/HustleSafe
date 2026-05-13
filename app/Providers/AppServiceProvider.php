<?php

namespace App\Providers;

use App\Listeners\RecordUserLogin;
use App\Models\Portfolio;
use App\Models\Quest;
use App\Models\Review;
use App\Models\User;
use App\Models\UserVerification;
use App\Observers\PortfolioObserver;
use App\Observers\UserObserver;
use App\Observers\UserVerificationObserver;
use App\Policies\PortfolioPolicy;
use App\Policies\QuestPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\UserVerificationPolicy;
use App\Services\TrustScoreOrchestrator;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(UserVerification::class, UserVerificationPolicy::class);
        Gate::policy(Portfolio::class, PortfolioPolicy::class);
        Gate::policy(Quest::class, QuestPolicy::class);

        User::observe(UserObserver::class);
        Portfolio::observe(PortfolioObserver::class);
        UserVerification::observe(UserVerificationObserver::class);

        Event::listen(Login::class, RecordUserLogin::class);

        Event::listen(Verified::class, function (Verified $event): void {
            $userId = $event->user->getKey();
            dispatch(function () use ($userId): void {
                $user = User::query()->find($userId);
                if ($user !== null) {
                    app(TrustScoreOrchestrator::class)->recalculate($user->fresh());
                }
            })->afterResponse();
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip().'|'.$request->path());
        });
    }
}
