<?php

namespace App\Http\Middleware;

use App\Services\ClientOutstandingActionsService;
use App\Support\Admin\AdminManagementRegistry;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\UserNotificationPresenter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $impersonation = $request->session()->get('impersonation');
        if (is_array($impersonation) && isset($impersonation['admin_id'], $impersonation['last_activity_at'])) {
            $lastActivity = Carbon::parse($impersonation['last_activity_at']);
            if ($lastActivity->lt(now()->subMinutes(30))) {
                $admin = User::query()->find($impersonation['admin_id']);
                if ($admin !== null) {
                    Auth::login($admin);
                }
                $request->session()->forget('impersonation');
                $impersonation = null;
            } else {
                $impersonation['last_activity_at'] = now()->toIso8601String();
                $request->session()->put('impersonation', $impersonation);
            }
        }

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
            'admin_entry_url' => static function () use ($request) {
                $user = $request->user();
                if ($user === null || $user->role?->slug !== 'super_admin') {
                    return null;
                }

                return route('admin.dashboard');
            },
            'operations_entry_url' => static function () use ($request) {
                $user = $request->user();
                if ($user === null || $user->role?->slug !== 'admin') {
                    return null;
                }

                return route('operations.dashboard');
            },
            'admin_management_nav' => static function () use ($request) {
                $user = $request->user();
                if ($user === null || $user->role?->slug !== 'super_admin') {
                    return null;
                }

                return AdminManagementRegistry::sidebarNavigation();
            },
            'impersonation' => fn () => $impersonation,
        ];
    }
}
