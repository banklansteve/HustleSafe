<?php

namespace App\Http\Middleware;

use App\Services\ClientOutstandingActionsService;
use App\Services\ReviewModeration\ReviewAmendmentService;
use App\Services\Support\CustomerSupportService;
use App\Services\Admin\ContentManagementService;
use App\Support\Admin\AdminManagementRegistry;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\UserNotificationPresenter;
use App\Models\User;
use App\Support\InertiaAuthUser;
use App\Support\BroadcastClientConfig;
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
                'user' => fn () => InertiaAuthUser::for($request->user()),
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
                if ($user === null || $user->role?->slug === 'admin') {
                    return 0;
                }

                return app(UserNotificationPresenter::class)->groupedUnreadCount($user);
            },
            'recentNotifications' => static function () use ($request) {
                $user = $request->user();
                if ($user === null || $user->role?->slug === 'admin') {
                    return [];
                }

                return app(UserNotificationPresenter::class)->recentForNav($user, 8);
            },
            'flash' => [
                'newsletter' => fn () => $request->session()->get('newsletter'),
                'success' => fn () => $request->session()->get('success'),
                'status' => fn () => $request->session()->get('status'),
                'token' => fn () => $request->session()->has('success') || $request->session()->has('status') || $request->session()->has('errors') ? uniqid('flash_', true) : null,
                'proposal_next_steps' => fn () => $request->session()->get('proposal_next_steps'),
                'quest_submitted_next_steps' => fn () => $request->session()->get('quest_submitted_next_steps'),
                'quality_gate_issues' => fn () => $request->session()->get('quality_gate_issues'),
                'show_escrow_funding_notice' => fn () => (bool) $request->session()->pull('show_escrow_funding_notice'),
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
            'platform_fee_percent' => static function () {
                return \App\Support\PlatformSettings::platformFeePercent();
            },
            'announcement_banner' => static function () use ($request) {
                if ($request->user()?->role?->slug === 'admin') {
                    return null;
                }

                return app(ContentManagementService::class)->activeBannerFor($request->user());
            },
            'impersonation' => fn () => $impersonation,
            'broadcast' => static fn () => $request->user() ? BroadcastClientConfig::forRequest() : null,
            'reverb' => static fn () => $request->user() ? BroadcastClientConfig::forRequest() : null,
            'review_amendment_prompts' => static function () use ($request) {
                $user = $request->user();
                if ($user === null || ! in_array($user->role?->slug, ['client', 'freelancer'], true)) {
                    return [];
                }

                return app(ReviewAmendmentService::class)->pendingPromptsFor($user);
            },
            'customer_support_widget' => static function () use ($request) {
                $user = $request->user();
                if ($user === null || ! in_array($user->role?->slug, ['client', 'freelancer'], true)) {
                    return ['enabled' => false];
                }

                $service = app(CustomerSupportService::class);
                if (! $service->tablesReady()) {
                    return ['enabled' => false];
                }

                return $service->widgetBootstrap($user);
            },
        ];
    }
}
