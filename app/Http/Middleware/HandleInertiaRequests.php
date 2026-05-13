<?php

namespace App\Http\Middleware;

use App\Services\FreelancerWorkspaceReadinessService;
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

                return $user === null ? 0 : $user->unreadNotifications()->count();
            },
            'flash' => [
                'newsletter' => fn () => $request->session()->get('newsletter'),
                'success' => fn () => $request->session()->get('success'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
