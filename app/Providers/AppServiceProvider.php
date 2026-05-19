<?php

namespace App\Providers;

use App\Listeners\RecordUserLogin;
use App\Models\Portfolio;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\Review;
use App\Models\User;
use App\Models\UserVerification;
use App\Observers\PortfolioObserver;
use App\Observers\UserObserver;
use App\Observers\UserVerificationObserver;
use App\Policies\PortfolioPolicy;
use App\Policies\QuestDisputePolicy;
use App\Policies\QuestOfferPolicy;
use App\Policies\QuestPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\UserVerificationPolicy;
use App\Services\TrustScoreOrchestrator;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $scheme = config('app.url_scheme');
        if (is_string($scheme) && in_array($scheme, ['http', 'https'], true)) {
            URL::forceScheme($scheme);
        }

        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(UserVerification::class, UserVerificationPolicy::class);
        Gate::policy(Portfolio::class, PortfolioPolicy::class);
        Gate::policy(Quest::class, QuestPolicy::class);
        Gate::policy(QuestOffer::class, QuestOfferPolicy::class);
        Gate::policy(QuestDispute::class, QuestDisputePolicy::class);

        Route::bind('contact', function (?string $value) {
            if ($value === null || $value === '') {
                return null;
            }

            return User::query()
                ->where(function ($q) use ($value): void {
                    $q->where('slug', $value);
                    if (ctype_digit($value)) {
                        $q->orWhere('id', (int) $value);
                    }
                })
                ->firstOrFail();
        });

        User::observe(UserObserver::class);
        Portfolio::observe(PortfolioObserver::class);
        UserVerification::observe(UserVerificationObserver::class);

        Event::listen(Login::class, RecordUserLogin::class);

        Event::listen(Verified::class, function (Verified $event): void {
            $userId = $event->user->getKey();
            dispatch(function () use ($userId): void {
                $user = User::query()->find($userId);
                if ($user !== null) {
                    $user->userVerifications()->updateOrCreate(
                        ['category' => 'email', 'verification_type' => 'email'],
                        [
                            'status' => 'verified',
                            'submitted_by' => $user->id,
                            'submitted_at' => $user->email_verified_at ?? now(),
                            'reviewed_at' => $user->email_verified_at ?? now(),
                            'metadata' => ['email' => $user->email, 'source' => 'email_confirmation_link'],
                        ],
                    );
                    app(VerificationEngineService::class)->recalculate($user->fresh(), null, 'Email verification completed.');
                    app(TrustScoreOrchestrator::class)->recalculate($user->fresh());
                }
            })->afterResponse();
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip().'|'.$request->path());
        });
    }
}
