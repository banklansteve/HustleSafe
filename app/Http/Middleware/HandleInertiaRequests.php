<?php

namespace App\Http\Middleware;

use App\Services\ClientOutstandingActionsService;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\UserNotificationPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'freelancerWorkspace' => static function () use ($request) {
                $user = $request->user();
                if ($user === null || $user->role?->slug !== 'freelancer') {
                    return null;
                }

                return app(FreelancerWorkspaceReadinessService::class)->toInertiaProps($user);
            },
            'unreadNotificationsCount' => static function () use ($request) {
                $user = $request->user();
                if ($user === null) {
                    return 0;
                }

                return app(UserNotificationPresenter::class)->groupedUnreadCount($user);
            },
            'recentNotifications' => static function () use ($request) {
                $user = $request->user();
                if ($user === null) {
                    return [];
                }

                return app(UserNotificationPresenter::class)->recentForNav($user, 8);
            },
            'flash' => [
                'newsletter' => fn () => $request->session()->get('newsletter'),
                'success' => fn () => $request->session()->get('success'),
                'status' => fn () => $request->session()->get('status'),
                'proposal_next_steps' => fn () => $request->session()->get('proposal_next_steps'),
                'quest_submitted_next_steps' => fn () => $request->session()->get('quest_submitted_next_steps'),
            ],
            'client_outstanding' => static function () use ($request) {
                $user = $request->user();
                if ($user === null || $user->role?->slug !== 'client') {
                    return [];
                }

                return app(ClientOutstandingActionsService::class)->items($user);
            },
        ];
    }
}
