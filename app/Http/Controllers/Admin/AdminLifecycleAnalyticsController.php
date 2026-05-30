<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\UserLifecycleAnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class AdminLifecycleAnalyticsController extends Controller
{
    public function __invoke(UserLifecycleAnalyticsService $analytics): Response
    {
        abort_unless(request()->user()?->role?->slug === 'super_admin', 403);

        return Inertia::render('Admin/Lifecycle/Index', [
            'analytics' => $analytics->payload(),
        ]);
    }
}
