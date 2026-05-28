<?php

namespace App\Providers;

use App\Listeners\RecordUserLogin;
use App\Models\Portfolio;
use App\Models\ModerationCase;
use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\QuestConversationThread;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Services\TrustRisk\TrustRiskActivityHook;
use App\Support\TrustRisk\UserRiskScoreDispatcher;
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

        $this->registerTrustRiskModelHooks();

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

    private function registerTrustRiskModelHooks(): void
    {
        $hook = fn () => app(TrustRiskActivityHook::class);

        Quest::created(function (Quest $quest) use ($hook): void {
            $hook()->questPosted($quest);
        });

        QuestOffer::created(function (QuestOffer $offer) use ($hook): void {
            $hook()->proposalSubmitted($offer);
        });

        QuestConversationThread::created(function (QuestConversationThread $thread) use ($hook): void {
            $hook()->conversationStarted($thread);
        });

        QuestDispute::created(function (QuestDispute $dispute) use ($hook): void {
            $hook()->disputeOpened($dispute);
        });

        ModerationCase::created(function (ModerationCase $case): void {
            if ($case->subject_user_id) {
                UserRiskScoreDispatcher::dispatch((int) $case->subject_user_id);
            }
        });

        PaymentEscrow::saved(function (PaymentEscrow $escrow) use ($hook): void {
            if ($escrow->wasChanged('funded_at') && $escrow->funded_at !== null) {
                $hook()->contractInitiated(
                    (int) $escrow->client_id,
                    (int) $escrow->freelancer_id,
                    (int) $escrow->id,
                );
            }
        });
    }
}
